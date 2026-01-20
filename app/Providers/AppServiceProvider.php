<?php

namespace App\Providers;

use App\Listeners\OtelHttpClientTracing;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

use Laratel\Opentelemetry\Services\TraceService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Globals;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Http;
use OpenTelemetry\Context\Context;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AppServiceProvider extends ServiceProvider
{
    protected $listen = [
        RequestSending::class => [
            OtelHttpClientTracing::class . '@onRequestSending',
        ],
        ResponseReceived::class => [
            OtelHttpClientTracing::class . '@onResponseReceived',
        ],
        ConnectionFailed::class => [
            OtelHttpClientTracing::class . '@onConnectionFailed',
        ],
    ];
    /** @var array<int, SpanInterface> */
    private static array $httpSpans = [];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            // создаём child span внутри текущего активного HTTP span
            $tracer = Globals::tracerProvider()->getTracer('laravel.db');

            $span = $tracer->spanBuilder('DB '.$event->connectionName)
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->startSpan();

            $span->setAttribute('db.system', $this->guessDbSystem($event->connection->getDriverName()));
            $span->setAttribute('db.name', (string)($event->connection->getDatabaseName() ?? ''));
            $span->setAttribute('db.statement', $event->sql);
            $span->setAttribute('db.operation', $this->guessOperation($event->sql));
            $span->setAttribute('db.connection', $event->connectionName);
            $span->setAttribute('db.duration_ms', (float)$event->time);

            // если хочешь — добавь bindings (аккуратно: могут быть секреты!)
            // $span->setAttribute('db.bindings', json_encode($event->bindings));

            $span->end();
        });

    }

    private function guessOperation(string $sql): string
    {
        $sql = ltrim($sql);
        $op = strtoupper(strtok($sql, " \n\t(") ?: '');
        return $op ?: 'UNKNOWN';
    }

    private function guessDbSystem(string $driver): string
    {
        return match (strtolower($driver)) {
            'mysql' => 'mysql',
            'pgsql' => 'postgresql',
            'sqlite' => 'sqlite',
            'sqlsrv' => 'mssql',
            default => $driver,
        };
    }
}
