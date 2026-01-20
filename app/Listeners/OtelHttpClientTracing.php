<?php

namespace App\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Events\ConnectionFailed;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;

final class OtelHttpClientTracing
{
    /**
     * Храним спаны между событиями (request -> response/failed).
     * Ключ — spl_object_id($event->request).
     */
    private static array $inflight = [];

    public function onRequestSending(RequestSending $event): void
    {
        // Важно: берём Tracer из Globals — если твой пакет правильно инициализировал SDK,
        // то это будет ТОТ ЖЕ provider/exporter и спан уйдёт в collector.
        $tracer = Globals::tracerProvider()->getTracer('laravel-http-client', '1.0.0');

        $req = $event->request;

        $url = $req->url();
        $method = strtoupper($req->method());

        $span = $tracer->spanBuilder("HTTP {$method}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setParent(Context::getCurrent())
            ->startSpan();

        // Базовые атрибуты по семантике OTel
        $span->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $method);
        $span->setAttribute(TraceAttributes::URL_FULL, $url);

        // Хост/порт (не критично, но полезно)
        $parts = parse_url($url);
        if (is_array($parts)) {
            if (!empty($parts['host'])) {
                $span->setAttribute(TraceAttributes::SERVER_ADDRESS, $parts['host']);
            }
            if (!empty($parts['port'])) {
                $span->setAttribute(TraceAttributes::SERVER_PORT, (int) $parts['port']);
            }
        }

        // Активируем спан, чтобы всё внутри запроса (если вдруг) цеплялось как child
        $scope = $span->activate();

        self::$inflight[spl_object_id($req)] = [
            'span' => $span,
            'scope' => $scope,
        ];
    }

    public function onResponseReceived(ResponseReceived $event): void
    {
        $req = $event->request;
        $key = spl_object_id($req);

        if (!isset(self::$inflight[$key])) {
            return;
        }

        $span = self::$inflight[$key]['span'];
        $scope = self::$inflight[$key]['scope'];

        $resp = $event->response;

        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $resp->status());

        if ($resp->failed()) {
            $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$resp->status()}");
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        // Закрываем
        $scope->detach();
        $span->end();

        unset(self::$inflight[$key]);
    }

    public function onConnectionFailed(ConnectionFailed $event): void
    {
        $req = $event->request;
        $key = spl_object_id($req);

        if (!isset(self::$inflight[$key])) {
            return;
        }

        $span = self::$inflight[$key]['span'];
        $scope = self::$inflight[$key]['scope'];

        $span->setStatus(StatusCode::STATUS_ERROR, 'Connection failed');

        $scope->detach();
        $span->end();

        unset(self::$inflight[$key]);
    }
}
