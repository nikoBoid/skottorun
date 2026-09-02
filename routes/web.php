<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\JournalPageController;
use App\Http\Controllers\JournalTopicController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RelicController;
use App\Http\Controllers\RelicRevelationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? to_route('dashboard')
    : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('players', [PlayerController::class, 'store'])->name('players.store');
    Route::resource('inventory', InventoryItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['inventory' => 'inventoryItem']);
    Route::resource('relics', RelicController::class)->only(['store', 'update', 'destroy']);
    Route::patch('relic-revelations/{revelation}', [RelicRevelationController::class, 'update'])
        ->name('relic-revelations.update');
    Route::post('journal/topics', [JournalTopicController::class, 'store'])->name('journal-topics.store');
    Route::delete('journal/topics/{journalTopic}', [JournalTopicController::class, 'destroy'])->name('journal-topics.destroy');
    Route::post('journal/topics/{journalTopic}/pages', [JournalPageController::class, 'store'])->name('journal-pages.store');
    Route::patch('journal/pages/{journalPage}', [JournalPageController::class, 'update'])->name('journal-pages.update');
    Route::delete('journal/pages/{journalPage}', [JournalPageController::class, 'destroy'])->name('journal-pages.destroy');
});

require __DIR__.'/settings.php';
