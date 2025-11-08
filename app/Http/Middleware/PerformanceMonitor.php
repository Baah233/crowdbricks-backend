<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Process the request
        $response = $next($request);

        // Calculate metrics
        $executionTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
        $memoryUsed = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2); // MB

        // Log slow queries (>1 second)
        if ($executionTime > 1000) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime . 'ms',
                'memory_used' => $memoryUsed . 'MB',
                'user_id' => $request->user()?->id,
            ]);
        }

        // Add performance headers for debugging
        $response->headers->set('X-Execution-Time', $executionTime . 'ms');
        $response->headers->set('X-Memory-Usage', $memoryUsed . 'MB');

        return $response;
    }
}
