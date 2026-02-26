<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('templates', TemplateController::class)->middleware('permission:templates');
    Route::resource('campaigns', CampaignController::class)->middleware('permission:campaigns');

    Route::get('/templates/create', [TemplateController::class, 'create'])
        ->middleware('permission:add template')
        ->name('templates.create');

    Route::get('/campaigns/create', [CampaignController::class, 'create'])
        ->middleware('permission:add campaign')
        ->name('campaigns.create');

    Route::resource('users', UserController::class)->except(['create', 'edit', 'show'])
        ->middleware('permission:users');

    Route::get('/users/create', [UserController::class, 'create'])
        ->middleware('permission:add user')
        ->name('users.create');

    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:edit user')
        ->name('users.edit');

    Route::get('/error/403', fn () => Inertia::render('Errors/Error403'))->name('error.403');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('/templates/send-test', [TemplateController::class, 'testSend'])->name('templates.sendTest');

});

Route::get(
    '/campaigns/companies/{company}/channels',
    [CampaignController::class, 'channels']
)->middleware(['auth', 'permission:campaigns']);

Route::get(
    '/campaigns/companies/{company}/channels/{channel}/templates',
    [CampaignController::class, 'templates']
)->middleware(['auth', 'permission:campaigns']);

Route::get(
    '/campaigns/templates/{template}',
    [CampaignController::class, 'templatePreview']
)->middleware('auth');

Route::get('/campaigns/{campaign}/recipients/export', [CampaignController::class, 'exportRecipients'])
  ->middleware(['auth', 'permission:campaigns'])
  ->name('campaigns.recipients.export');

Route::middleware(['auth', 'permission:chat'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');

    Route::get('/chat/threads', [ChatController::class, 'threads'])->name('chat.threads');
    Route::get('/chat/messages/{threadId}', [ChatController::class, 'messages'])->name('chat.messages'); 
});

Route::middleware(['auth'])->patch('/chat/threads/{thread}/close', [ChatController::class, 'closeThread']);

Route::middleware(['auth'])->group(function () {
    Route::get('/chat/history', [ChatController::class, 'historyByPhone']);
});

/*
Route::get(
    '/campaigns/recipients/{recipient}/test-send',
    [CampaignController::class, 'testSendFromRecipient']
)->middleware(['auth']);
*/

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';