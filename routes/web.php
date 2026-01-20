<?php

use App\Http\Controllers\NewController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/demo/users', [NewController::class,"testMe"]);
