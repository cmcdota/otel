<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Laratel\Opentelemetry\Middleware\OpenTelemetryMetricsMiddleware;
use Laratel\Opentelemetry\Middleware\OpenTelemetryTraceMiddleware;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(OpenTelemetryMetricsMiddleware::class);
        $middleware->append(OpenTelemetryTraceMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e) {
            $span = Span::getCurrent();
            if ($span) {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            }
        });
    })->create();
