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

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('inventory', InventoryItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['inventory' => 'inventoryItem']);
    Route::post('journal/topics', [JournalTopicController::class, 'store'])->name('journal-topics.store');
    Route::delete('journal/topics/{journalTopic}', [JournalTopicController::class, 'destroy'])->name('journal-topics.destroy');
    Route::post('journal/topics/{journalTopic}/pages', [JournalPageController::class, 'store'])->name('journal-pages.store');
    Route::patch('journal/pages/{journalPage}', [JournalPageController::class, 'update'])->name('journal-pages.update');
    Route::delete('journal/pages/{journalPage}', [JournalPageController::class, 'destroy'])->name('journal-pages.destroy');
});

Route::middleware(['auth', 'dm'])->prefix('dm')->name('dm.')->group(function () {
    Route::get('players', [PlayerController::class, 'index'])->name('players.index');
    Route::post('players', [PlayerController::class, 'store'])->name('players.store');
    Route::patch('players/{character}', [PlayerController::class, 'update'])->name('players.update');

    Route::get('relics', [RelicController::class, 'index'])->name('relics.index');
    Route::post('relics', [RelicController::class, 'store'])->name('relics.store');
    Route::patch('relics/{relic}', [RelicController::class, 'update'])->name('relics.update');
    Route::delete('relics/{relic}', [RelicController::class, 'destroy'])->name('relics.destroy');
    Route::patch('relic-revelations/{revelation}', [RelicRevelationController::class, 'update'])
        ->name('relic-revelations.update');
});
