<?php

use App\Http\Controllers\RpcController;
use Illuminate\Support\Facades\Route;

// Halaman aplikasi (front-end Resik Collect — disajikan apa adanya, tanpa Blade).
Route::get('/', function () {
    $path = resource_path('resik/index.html');
    abort_unless(is_file($path), 404);
    return response(file_get_contents($path), 200, ['Content-Type' => 'text/html; charset=utf-8']);
});

// API — padanan google.script.run.rpc(a, body, q, tok).
Route::match(['get', 'post'], '/rpc', [RpcController::class, 'handle']);
