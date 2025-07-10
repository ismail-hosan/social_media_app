<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryReact extends Model
{
    protected $fillable = ['user_id', 'story_id', 'type'];
    protected $hidden = ['id', 'story_id', 'user_id', 'created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
