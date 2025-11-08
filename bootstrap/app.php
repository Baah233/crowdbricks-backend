<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // add your API routes here
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ Enable Laravel's default CORS handler
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        
        // ✅ Performance monitoring for API routes
        $middleware->appendToGroup('api', \App\Http\Middleware\PerformanceMonitor::class);
        
        // ✅ Audit logging for all developer actions
        $middleware->appendToGroup('api', \App\Http\Middleware\AuditLog::class);
        
        // ✅ Rate limiting aliases
        $middleware->alias([
            'audit.log' => \App\Http\Middleware\AuditLog::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
