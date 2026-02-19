<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.company.{companyId}', function ($user, $companyId) {
    return true;
});

Broadcast::channel('chat.thread.{threadId}', function ($user, $threadId) {
    return true;
});