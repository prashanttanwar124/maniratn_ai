<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Authenticated Protected Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Central Hub Dashboard & Playground
    Route::get('/', [AiAssistantController::class, 'index'])->name('dashboard');

    // API Key Management Endpoints
    Route::post('/api/keys', [AiAssistantController::class, 'storeKey'])->name('keys.store');
    Route::post('/api/keys/{id}/toggle', [AiAssistantController::class, 'toggleKey'])->name('keys.toggle');
    Route::delete('/api/keys/{id}', [AiAssistantController::class, 'deleteKey'])->name('keys.destroy');

    // User & Access Control Management
    Route::get('/users', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('users.destroy');

    // Profile & Password Security
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Public Voice & Tool Calling Endpoints (Secured via Bearer Secret Token in Controller)
Route::post('/ai/chat', [AiAssistantController::class, 'chat'])->name('ai.chat');
Route::post('/api/ai/chat', [AiAssistantController::class, 'chat'])->name('api.ai.chat');
Route::post('/api/ai/tts', [AiAssistantController::class, 'tts'])->name('api.ai.tts');

