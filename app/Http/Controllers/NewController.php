<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

class NewController
{
    public function testMe()
    {
        Log::channel('otel')->info(
            'Laravel OTEL test 1',
            [
                'user_id' => 123,
                'feature' => 'opentelemetry-demo',
            ]
        );

        return view('welcome');
    }
}
