<?php

declare(strict_types=1);

use App\Http\Controllers\ExtensionController;
use Illuminate\Support\Facades\Route;

// The browser extension, with a token made under Settings → Browser extension. Reading only.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('extension')->group(function (): void {
    Route::get('/profile', [ExtensionController::class, 'profile'])->name('extension.profile');
    Route::get('/application', [ExtensionController::class, 'application'])->name('extension.application');
    Route::get('/applications/{id}/documents/{document}', [ExtensionController::class, 'document'])
        ->whereNumber('id')->whereIn('document', ['cv', 'letter'])->name('extension.document');
});
