<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
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
        $this->reportable(function (Throwable $e) {
            // Report to Sentry if configured
            if (app()->bound('sentry') && $this->shouldReport($e)) {
                app('sentry')->captureException($e);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        $response = parent::render($request, $e);
        $status = $response->getStatusCode();

        // Only render custom error pages for web requests (not API)
        if (!$request->expectsJson() && in_array($status, [404, 429, 500, 503])) {
            return Inertia::render("Errors/{$status}", [
                'status' => $status,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        }

        return $response;
    }
}
