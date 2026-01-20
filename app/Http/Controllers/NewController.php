<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewController
{
    public function testMe()
    {
        \Log::info('about to call http client');

        $r = Http::withOptions(['verify' => false])
            ->get('https://httpbin.org/get', ['user' => '123']);

        // какие-то запросы в БД (у тебя они уже есть)
        // ...

        return response()->json([
            'httpbin' => $r->json(),
        ]);
    }
}
