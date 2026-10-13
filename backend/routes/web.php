<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Session-based Breeze routes (password reset, email verification links).
require __DIR__.'/auth.php';
