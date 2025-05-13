<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect('/');
})->name('login');

// Rute untuk halaman pengujian laporan foto
Route::get('/test-report', function () {
    return file_get_contents(public_path('test-report.html'));
});