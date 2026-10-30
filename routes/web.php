<?php

use App\Http\Controllers\AlchemyController;
use App\Http\Controllers\MemoryController;
use App\Models\Tribute;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/altar/{tribute}', fn (Tribute $tribute) => view('altar', ['tribute' => $tribute]))
    ->name('altar');

Route::prefix('api')->group(function () {
    Route::post('/memories', [MemoryController::class, 'store']);
    Route::post('/memories/audio', [MemoryController::class, 'audio']);
    Route::post('/memories/{memory}/alchemize', [AlchemyController::class, 'start']);
    Route::get('/tributes/{tribute}', [AlchemyController::class, 'show']);
});