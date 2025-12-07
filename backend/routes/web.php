<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});


require __DIR__.'/auth.php';

Route::get('docs/api-docs.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (!file_exists($path)) {
        abort(404, 'API docs not found');
    }

    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
});
