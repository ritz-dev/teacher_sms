<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});

// Health check routes
Route::get('/ping', function () {
    return response()->json(['message' => 'pong'], 200);
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Teacher Service',
        'version' => '1.0.0',
        'database' => $dbStatus,
        'environment' => app()->environment()
    ], 200);
});

Route::any('gateway/{endpoint}', function ($endpoint) {
    // Forward to /api/$endpoint
    $method = request()->method();
    $data = request()->all();
    $url = url('/api/' . $endpoint);
    
    $response = Http::timeout(30)->$method($url, $data);
    
    return response($response->body(), $response->status())
        ->header('Content-Type', $response->header('Content-Type'));
})->where('endpoint', '.*');
