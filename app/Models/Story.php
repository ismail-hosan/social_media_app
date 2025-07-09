<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    protected $fillable = ['user_id', 'content', 'file_url', 'slug'];
    protected $appends = ['is_me'];

    public function getFileUrlAttribute($value)
    {
        return $value ? asset($value) : null;
    }

    public function getSlugAttribute($value)
    {
        return $value ? url('/api/story/story/' . $value) : null;
    }


    public function react()
    {
        return $this->hasMany(StoryReact::class, 'story_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function getIsMeAttribute()
    {
        return auth()->check() && $this->user_id === auth()->id();
    }
}
