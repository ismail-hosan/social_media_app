<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Namu\WireChat\Models\Conversation;

class GroupRequest extends Model
{
    protected $fillable = ['user_id', 'conversation_id'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
