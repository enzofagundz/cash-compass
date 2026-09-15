<?php

use Illuminate\Support\Facades\Route;

Route::redirect('dashboard', '/', 301);
Route::redirect('settings', '/profile', 301);

Route::redirect('admin', '/', 301);
Route::any('admin/{path}', fn (string $path) => redirect('/'.$path, 301))
    ->where('path', '.*');
