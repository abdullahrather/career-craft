<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/locale', LocaleController::class)->name('locale');
