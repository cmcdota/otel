<?php

namespace App\Logging;

use Monolog\Logger;
use OpenTelemetry\API\Trace\Span;

class AddTraceContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (array $record) {
            $span = Span::getCurrent();
            $ctx = $span ? $span->getContext() : null;

            if ($ctx && $ctx->isValid()) {
                $record['extra']['trace_id'] = $ctx->getTraceId();
                $record['extra']['span_id']  = $ctx->getSpanId();
            }

            return $record;
        });
    }
}
