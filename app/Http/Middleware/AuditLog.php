<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    /**
     * Actions that should be audited
     */
    private $auditableActions = [
        'POST', 'PUT', 'PATCH', 'DELETE'
    ];

    /**
     * High-risk endpoints
     */
    private $highRiskPatterns = [
        '/withdraw',
        '/payout',
        '/delete',
        '/update',
        '/kyc',
        '/verification',
    ];

    /**
     * Handle an incoming request and log auditable actions
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit specific HTTP methods
        if (in_array($request->method(), $this->auditableActions)) {
            $this->logAction($request, $response);
        }

        return $response;
    }

    /**
     * Log the action to audit_logs table
     */
    private function logAction(Request $request, Response $response)
    {
        try {
            $user = Auth::user();
            $action = $this->determineAction($request);
            $riskLevel = $this->calculateRiskLevel($request);

            DB::table('audit_logs')->insert([
                'user_id' => $user?->id,
                'action' => $action,
                'model_type' => $this->extractModelType($request),
                'model_id' => $this->extractModelId($request),
                'description' => $this->generateDescription($request, $action),
                'old_values' => null, // Could be populated by observers
                'new_values' => json_encode($request->except(['password', 'token', 'secret'])),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'risk_level' => $riskLevel,
                'flagged' => $this->shouldFlag($request, $riskLevel),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to not break the request
            \Log::error('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine the action being performed
     */
    private function determineAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        if (str_contains($path, 'withdraw')) return 'withdrawal_requested';
        if (str_contains($path, 'payout')) return 'payout_processed';
        if (str_contains($path, 'kyc')) return 'kyc_submitted';
        if (str_contains($path, 'project') && $method === 'POST') return 'project_created';
        if (str_contains($path, 'project') && in_array($method, ['PUT', 'PATCH'])) return 'project_updated';
        if ($method === 'DELETE') return 'record_deleted';

        return strtolower($method) . '_' . str_replace('/', '_', $path);
    }

    /**
     * Extract model type from request
     */
    private function extractModelType(Request $request): ?string
    {
        $path = $request->path();
        
        if (str_contains($path, 'project')) return 'Project';
        if (str_contains($path, 'investment')) return 'Investment';
        if (str_contains($path, 'user')) return 'User';
        if (str_contains($path, 'transaction')) return 'Transaction';

        return null;
    }

    /**
     * Extract model ID from request
     */
    private function extractModelId(Request $request): ?int
    {
        // Try to get ID from route parameters
        $route = $request->route();
        
        if ($route && $route->parameter('id')) {
            return (int) $route->parameter('id');
        }

        if ($route && $route->parameter('project')) {
            return (int) $route->parameter('project');
        }

        return null;
    }

    /**
     * Generate human-readable description
     */
    private function generateDescription(Request $request, string $action): string
    {
        $user = Auth::user();
        $userName = $user ? $user->name : 'Guest';

        return "{$userName} performed action: {$action} from IP {$request->ip()}";
    }

    /**
     * Calculate risk level based on action
     */
    private function calculateRiskLevel(Request $request): string
    {
        $path = $request->path();

        // Critical actions
        if (str_contains($path, 'withdraw') || str_contains($path, 'payout')) {
            return 'critical';
        }

        // High risk actions
        foreach ($this->highRiskPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return 'high';
            }
        }

        // Medium risk for updates
        if (in_array($request->method(), ['PUT', 'PATCH'])) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Determine if action should be flagged for review
     */
    private function shouldFlag(Request $request, string $riskLevel): bool
    {
        // Flag all critical actions
        if ($riskLevel === 'critical') {
            return true;
        }

        // Flag rapid-fire requests (potential automation/attack)
        $user = Auth::user();
        if ($user) {
            $recentLogs = DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('created_at', '>', now()->subMinutes(5))
                ->count();

            if ($recentLogs > 20) {
                return true; // More than 20 actions in 5 minutes
            }
        }

        return false;
    }
}
