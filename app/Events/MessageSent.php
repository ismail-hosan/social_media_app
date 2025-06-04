<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $message;

    public function __construct(Chat $chat, Message $message)
    {
        $this->chat = $chat;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new Channel('chat.' . $this->chat->id); // Broadcast to the chat channel
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message->message,
            'sender' => $this->message->sender->name,
            'timestamp' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
