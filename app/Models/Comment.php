<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = ['user_id', 'body', 'commentable_id', 'commentable_type', 'parent_id'];

    protected $hidden = ['created_at', 'parent_id', 'updated_at', 'commentable_id', 'commentable_type'];
    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->hasMany(Comment::class, 'parent_id')->with('replies');
    }

    /**
     * Replies to this comment.
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->with(['replies', 'user:id,name,avatar']); // recursive + user
    }

    public function react()
    {
        return $this->hasMany(CommentReact::class, 'comment_id');
    }

    public function repliesRecursive()
    {
        return $this->hasMany(Comment::class, 'parent_id')->with('repliesRecursive');
    }
}
