<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Traits\apiresponse;
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
                    ->select('id', 'name', 'avatar')
                    ->get();

                return $this->success($addIsLikeFlag($users), 'Following users fetched', 200);

            case 'pending':
                $userIds = DB::table('follows')
                    ->where('follower_id', $userId)
                    ->where('status', 'pending')
                    ->pluck('user_id');

                $users = User::whereIn('id', $userIds)
                    ->select('id', 'name', 'avatar')
                    ->get();

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
                    ->select('id', 'name', 'avatar')
                    ->latest()
                    ->get();

                return $this->success($addIsLikeFlag($users), 'Potential friends fetched', 200);
        }
    }

    public function accept(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:follows,id'],
        ]);

        $followRequest = $this->follow->find($request->id);

        if ($followRequest->follower_id != auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to accept this request'
            ], 403);
        }

        // Mark as accepted (you'll need a column like 'status' => 'pending' | 'accepted')
        $followRequest->status = 'accepted';
        $followRequest->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Follow request accepted',
            'data' => $followRequest
        ], 200);
    }

    public function search(Request $request) {}


    public function whoToFollow(Request $request)
    {
        $userId = auth()->id();

        $followingIds = Follow::where('user_id', $userId)
            ->pluck('follower_id')
            ->toArray();
        // Get IDs of users the authenticated user is following

        // Get suggested users to follow (excluding already followed users and self)
        $usersToFollow = User::whereNotIn('id', $followingIds)
            ->inRandomOrder()
            ->where('is_admin', false)
            ->take(5)
            ->get();
        $usersToFollow->transform(function ($user) use ($followingIds) {
            $user->is_follow = in_array($user->id, $followingIds);
            return $user;
        });

        // Get posts only from followed users and self
        $posts = Post::whereIn('user_id', $followingIds)
            ->where('user_id', '!=', $userId)
            ->with(['user', 'tags', 'images'])
            ->withCount(['likes', 'comments', 'repost'])
            ->with(['bookmarks' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->latest()
            ->paginate(5);

        // Add bookmark and interaction statuses
        $posts->getCollection()->transform(function ($post) {
            $post->is_bookmarked = $post->bookmarks->isNotEmpty();
            $post->is_repost = $post->repost->isNotEmpty();
            $post->is_likes = $post->likes->isNotEmpty();
            unset($post->repost);
            unset($post->bookmarks);
            unset($post->likes);
            return $post;
        });

        // Suggested users block
        $suggestedUsersItem = (object)[
            'type' => 'suggested_users',
            'users' => $usersToFollow,
        ];

        // Append suggested users item to posts
        $posts->setCollection(
            $posts->getCollection()->push($suggestedUsersItem)
        );

        return $this->success([
            'posts' => $posts,
        ], 'Data fetched successfully!', 200);
    }
}
