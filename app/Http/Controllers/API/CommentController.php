<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReact;
use App\Models\Post;
use App\Models\Reel;
use App\Services\Service;
use App\Traits\apiresponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    use apiresponse;
    public function index($id)
    {
        $comments = Comment::where('commentable_id', $id)
            ->with(['user:id,name,avatar', 'replies.user:id,name,avatar', 'react'])
            ->get()
            ->map(function ($comment) {
                $comment->time_ago = $comment->created_at->diffForHumans();
                $comment->react_count = $comment->react->count(); // Add react count here

                $comment->replies->map(function ($reply) {
                    $reply->time_ago = $reply->created_at->diffForHumans();
                    return $reply;
                });

                return $comment;
            });

        return $this->success($comments, 'Comments fetched successfully!', 200);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
            'commentable_id' => 'required|integer|exists:posts,id',
            'commentable_type' => 'required|in:post,reel',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        $model = $request->commentable_type === 'post' ? Post::class : Reel::class;

        $comment = Comment::create([
            'user_id' => auth()->user()->id,
            'body' => $request->comment,
            'commentable_id' => $request->commentable_id,
            'commentable_type' => $model,
        ]);
        return $this->success($comment, 'Comment added successfully!', 201);
    }

    public function reply(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $reply = $comment->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'commentable_id' => $comment->commentable_id,
            'commentable_type' => $comment->commentable_type,
        ]);

        return $this->success($reply, 'Reply added successfully.', 200);
    }

    public function react(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'react' => 'required|string|in:love,like',
        ]);

        $user = auth()->user();

        $existingReaction = CommentReact::where('user_id', $user->id)
            ->where('comment_id', $comment->id)
            ->first();

        if ($existingReaction) {
            $existingReaction->type = $validated['react'];
            $existingReaction->save();
        } else {
            CommentReact::create([
                'user_id' => $user->id,
                'comment_id' => $comment->id,
                'type' => $validated['react'],
            ]);
        }
        return $this->success(['react' => $validated['react']], 'Reaction saved successfully.', 200);
    }
}
