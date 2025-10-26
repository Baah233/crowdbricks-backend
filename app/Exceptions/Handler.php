<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected $levels = [];

    /**
     * A list of the exception types that are not reported.
     */
    protected $dontReport = [];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Customize response for unauthenticated users (API only).
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login')); // fallback for web
    }


    /**
     * Optionally, handle all exceptions in a unified JSON format for API.
     */
    public function render($request, Throwable $exception)
    {
        // Return JSON if it's an API request
        if ($request->is('api/*')) {
            $status = 500;

            if (method_exists($exception, 'getStatusCode')) {
                $status = $exception->getStatusCode();
            }

            return response()->json([
                'message' => $exception->getMessage() ?: 'Server Error',
                'exception' => class_basename($exception)
            ], $status);
        }

        // Otherwise fallback to default Laravel handling
        return parent::render($request, $exception);
    }
}
