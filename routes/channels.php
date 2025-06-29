<?php

use Illuminate\Support\Facades\Broadcast;
use Namu\WireChat\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if ($conversation && $user->belongsToConversation($conversation)) {
        return true; // Allow access
    }

    return false; // Deny access
});
