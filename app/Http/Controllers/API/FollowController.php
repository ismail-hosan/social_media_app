<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Traits\apiresponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FollowController extends Controller
{
    use apiresponse;

    private $follow;

    public function __construct(Follow $follow)
    {
        $this->follow = $follow;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'follower_id' => 'required|integer|exists:users,id|different:auth()->id()', // Don't allow the user to follow themselves
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user_id = auth()->id();


        $follower_id = $request->input('follower_id');
        if ($user_id === (int) $follower_id) {
            return $this->error([], 'You cannot follow yourself.', 422);
        }
        $existingFollow = $this->follow->where('user_id', $user_id)
            ->where('follower_id', $follower_id)
            ->first();

        if ($existingFollow) {
            $existingFollow->delete();
            $existingFollow->is_follow = false;
            return $this->success($existingFollow, 'Unfollowed the user', 200);
        }

        $follow = $this->follow->create([
            'user_id' => $user_id,
            'follower_id' => $follower_id,
        ]);
        $follow->is_follow = true;

        return $this->success($follow, 'You are now following this user', 200);
    }

    public function findfriends(Request $request)
    {
        $userId = auth()->id();

        // Validate the 'type' parameter
        $request->validate([
            'type' => ['required', Rule::in(['following', 'pending', 'potential'])],
        ]);

        $type = $request->input('type');

        // Get IDs of users liked by the current user
        $likedUserIds = DB::table('likes')
            ->where('user_id', $userId)
            ->where('likeable_type', User::class)
            ->pluck('likeable_id');

        // Helper to add 'is_like' flag to users collection
        $addIsLikeFlag = function ($users) use ($likedUserIds) {
            return $users->map(function ($user) use ($likedUserIds) {
                $user->is_like = $likedUserIds->contains($user->id);
                return $user;
            });
        };

        switch ($type) {
            case 'following':
                $userIds = DB::table('follows')
                    ->where('follower_id', $userId)
                    ->where('status', 'success')
                    ->pluck('user_id');

                $users = User::whereIn('id', $userIds)
                    ->select('id', 'name', 'avatar', 'base', 'location')
                    ->get();

                return $this->success($addIsLikeFlag($users), 'Following users fetched', 200);

            case 'pending':
                $userIds = DB::table('follows')
                    ->where('follower_id', $userId)
                    ->where('status', 'pending')
                    ->pluck('user_id');

                $users = User::whereIn('id', $userIds)
                    ->select('id', 'name', 'avatar', 'base', 'location', 'created_at')
                    ->get()
                    ->map(function ($user) {
                        $user->created_at_formatted = Carbon::parse($user->created_at)->diffForHumans(); // or ->format('Y-m-d H:i:s')
                        return $user;
                    });

                return $this->success($addIsLikeFlag($users), 'Pending friend requests fetched', 200);
            case 'potential':
                $following = DB::table('follows')
                    ->where('follower_id', $userId)
                    ->pluck('user_id');

                $pending = DB::table('follows')
                    ->where('follower_id', $userId)
                    ->where('status', 'pending')
                    ->pluck('user_id');

                $followers = DB::table('follows')
                    ->where('user_id', $userId)
                    ->pluck('follower_id');

                $connectedUsers = $following->merge($pending)->merge($followers)->unique();

                $users = User::where('id', '!=', $userId)
                    ->whereNotIn('id', $connectedUsers)
                    ->select('id', 'name', 'avatar', 'base', 'location')
                    ->latest()
                    ->get();

                return $this->success($addIsLikeFlag($users), 'Potential friends fetched', 200);
        }
    }

    public function accept(Request $request)
    {
        $request->validate([
            'follower_id' => ['required', 'exists:users,id'],
        ]);

        $followRequest = $this->follow
            ->where('user_id', $request->follower_id)
            ->where('follower_id', auth()->user()->id)
            ->where('status', 'pending')
            ->first();
        if (!$followRequest) {
            return $this->error([], 'Not Found this request', 404);
        }

        if ($followRequest->follower_id != auth()->id()) {
            return $this->error([], 'Unauthorized to accept this request', 403);
        }

        // Mark as accepted (you'll need a column like 'status' => 'pending' | 'accepted')
        $followRequest->status = 'success';
        $followRequest->save();

        return $this->success($followRequest, 'Follow request accepted', 200);
    }

    public function deny(Request $request)
    {
        $request->validate([
            'follower_id' => ['required', 'exists:users,id'],
        ]);

        $followRequest = $this->follow
            ->where('user_id', $request->follower_id)
            ->where('follower_id', auth()->user()->id)
            ->where('status', 'pending')
            ->first();
        if (!$followRequest) {
            return $this->error([], 'Not Found this request', 404);
        }

        if ($followRequest->follower_id != auth()->id()) {
            return $this->error([], 'Unauthorized to accept this request', 403);
        }

        // Mark as accepted (you'll need a column like 'status' => 'pending' | 'accepted')
        $followRequest->status = 'success';
        $followRequest->delete();

        return $this->success($followRequest, 'Follow request delete', 200);
    }

    public function search(Request $request) {}


    public function followersPosts(Request $request)
    {
        $userId = auth()->id();

        // Get IDs of users the authenticated user is following
        $followingIds = Follow::where('user_id', $userId)
            ->pluck('follower_id') // Make sure this is the correct column
            ->toArray();

        // Fetch posts from followed users
        $posts = Post::whereIn('user_id', $followingIds)
            ->where('status', 'active')
            ->with(['user:id,name,avatar,base,created_at'])
            ->withCount(['likes', 'comments'])
            ->with(['bookmarks' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->latest()
            ->paginate(5);
        // Transform each post
        $posts->getCollection()->transform(function ($post) {
            if ($post->user) {
                $post->user->join_date = $post->user->created_at
                    ? $post->user->created_at->format('d M Y')
                    : null;

                // Hide name and avatar if post is marked as unknown
                if ($post->unknown === 1) {
                    $post->user->name = null;
                    $post->user->avatar = null;
                }
            }

            $post->is_bookmarked = $post->bookmarks->isNotEmpty();
            $post->is_likes = $post->likes_count > 0;

            // Keep likes_count in response
            unset($post->bookmarks); // Remove only bookmarks relationship

            return $post;
        });

        return $this->success([
            'posts' => $posts,
        ], 'Posts from followed users fetched successfully!', 200);
    }
}
