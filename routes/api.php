<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\EventPushController;
use App\Http\Controllers\ChatReplyController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/external-event', [EventPushController::class, 'push']);
Route::get('/threads/{id}', [ThreadController::class, 'show']);
Route::post('/chat/threads/{threadId}/reply', [ChatReplyController::class, 'store']);
