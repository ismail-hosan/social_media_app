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
            ->whereNull('parent_id') // Only top-level comments
            ->with(['user:id,name,avatar', 'replies'])
            ->get()
            ->map(function ($comment) {
                return $this->transformComment($comment); // Recursive
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
            'body' => 'required|string|max:1000',
        ]);

        $reply = Comment::create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
            'parent_id' => $comment->id,
        ]);

        $reply->load(['user:id,name,avatar', 'replies']);

        return response()->json([
            'status' => true,
            'message' => 'Reply saved successfully!',
            'data' => $this->transformComment($reply),
        ], 201);
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

    private function transformComment($comment)
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $comment->user,
            'created_at' => $comment->created_at,
            'replies' => $comment->replies->map(function ($reply) {
                return $this->transformComment($reply); // Recursive
            })
        ];
    }
}
