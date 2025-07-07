<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReact extends Model
{
    protected $fillable = ['user_id','comment_id','type'];
}
