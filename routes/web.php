<?php

use App\Http\Controllers\SessionAudioController;
use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Route;

Route::bind('client', fn (string $value) => Client::query()->whereKey($value)->firstOrFail());
Route::bind('session', fn (string $value) => TherapySession::query()->whereKey($value)->firstOrFail());

Route::redirect('/', '/clients');

Route::livewire('/clients', 'pages::clients.index')->name('clients.index');
Route::livewire('/clients/create', 'pages::clients.form')->name('clients.create');
Route::livewire('/clients/{client}/edit', 'pages::clients.form')->name('clients.edit');
Route::livewire('/clients/{client}', 'pages::clients.show')->name('clients.show');

Route::livewire('/clients/{client}/sessions/create', 'pages::sessions.create')->name('sessions.create');
Route::livewire('/clients/{client}/sessions/{session}', 'pages::sessions.show')->name('sessions.show');
Route::livewire('/clients/{client}/sessions/{session}/edit', 'pages::sessions.edit')->name('sessions.edit');

Route::get('/clients/{client}/sessions/{session}/audio', SessionAudioController::class)
    ->name('sessions.audio');

Route::livewire('/settings', 'pages::settings')->name('settings');
