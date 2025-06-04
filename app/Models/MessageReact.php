<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReact extends Model
{
    protected $fillable = ['user_id', 'message_id', 'react'];
}
