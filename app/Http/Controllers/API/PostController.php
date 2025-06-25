<?php

namespace App\Http\Controllers\Api;

use App\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Mention;
use App\Models\Post;
use App\Models\Reel;
use App\Models\StoryImage;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\Notify;
use Illuminate\Support\Facades\Validator;
use App\Traits\apiresponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{

    use apiresponse;

    private $posts;

    public function __construct(Post $post)
    {
        $this->posts = $post;
    }
    public function index(Request $request)
    {
        $user_id = auth()->user()->id;
        if ($request->user_id) {
            $user_id = $request->user_id;
        }

        $liked = $request->liked; // Accept true or false

        $query = $this->posts
            ->where('user_id', $user_id)
            ->with(['user:id,name,avatar', 'tags'])
            ->withCount(['likes', 'comments'])
            ->with(['bookmarks' => function ($q) use ($user_id) {
                $q->where('user_id', $user_id);
            }]);

        if (!is_null($liked)) {
            if (filter_var($liked, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereHas('likes', function ($q) use ($user_id) {
                    $q->where('user_id', $user_id);
                });
            } else {
                $query->whereDoesntHave('likes', function ($q) use ($user_id) {
                    $q->where('user_id', $user_id);
                });
            }
        }

        $posts = $query->latest()->get();

        $posts->transform(function ($post) {
            $post->is_bookmarked = $post->bookmarks->isNotEmpty();
            $post->is_likes = $post->likes_count > 0;
            $post->created_date = $post->created_at->format('M d, Y');

            if ($post->unknown === 1) {
                unset($post->user);
                $post->user = (object) [
                    'name' => 'Unknown',
                    'avatar' => null
                ];
            }

            unset($post->repost);
            unset($post->bookmarks);

            return $post;
        });

        return $this->success($posts, 'Posts fetched successfully!', 200);
    }




    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'tag' => 'nullable|array',
            'unknown' => 'required|boolean',
            'image' => 'nullable|image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        try {
            $image_url = null;

            // Upload image if present
            if ($request->hasFile('image')) {
                $image_url = Helper::uploadImage($request->image, 'post');
            }

            // Create the post
            $post = $this->posts->create([
                'user_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'file_url' => $image_url,
                'unknown' => $request->unknown,
            ]);

            // Store tags from the "tag" array (not from description hashtags)
            if ($request->has('tag')) {
                foreach ($request->tag as $tagText) {
                    Tag::create([
                        'post_id' => $post->id,
                        'text' => $tagText,
                    ]);
                }
            }

            // Handle mentions from the description
            preg_match_all('/@(\w+)/', $request->description, $mentionMatches);
            $mentions = $mentionMatches[1];
            foreach ($mentions as $mentionText) {
                $mentionedUser = User::where('username', $mentionText)->first();
                if ($mentionedUser) {
                    Mention::create([
                        'post_id' => $post->id,
                        'user_id' => $user->id,
                        'mentioned_id' => $mentionedUser->id,
                    ]);
                }
            }

            return $this->success($post, 'Post created successfully!', 201);
        } catch (\Throwable $th) {
            return $this->error($th->getMessage(), 'Error');
        }
    }


    public function forYou(Request $request)
    {
        $userId = auth()->id();

        // Get suggested users to follow
        $followingIds = Follow::where('user_id', $userId)
            ->pluck('follower_id')
            ->toArray();

        $usersToFollow = User::whereNotIn('id', $followingIds)
            ->inRandomOrder()
            ->where('is_admin', false)
            ->take(5)
            ->get();
        $usersToFollow->transform(function ($user) use ($followingIds) {
            $user->is_follow = in_array($user->id, $followingIds);
            return $user;
        });

        // Get paginated posts
        $posts = $this->posts->whereNotIn('user_id', $followingIds)
            ->where('user_id', '!=', $userId)
            ->with(['user', 'tags', 'images'])
            ->withCount(['likes', 'comments', 'repost'])
            ->with(['bookmarks' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->latest()
            ->paginate(6);

        // Add bookmark status
        $posts->getCollection()->transform(function ($post) {
            $post->is_bookmarked = $post->bookmarks->isNotEmpty();
            $post->is_repost = $post->repost->isNotEmpty();
            $post->is_likes = $post->likes->isNotEmpty();
            unset($post->repost);
            unset($post->bookmarks);
            unset($post->likes);
            return $post;
        });

        // Create a virtual post item to hold suggested users
        $suggestedUsersItem = (object)[
            'type' => 'suggested_users',
            'users' => $usersToFollow,
        ];

        // Append to end of posts collection
        $posts->setCollection(
            $posts->getCollection()->push($suggestedUsersItem)
        );

        return $this->success([
            'posts' => $posts,
        ], 'Data fetched successfully!', 200);
    }

    public function mention(Request $request)
    {

        try {
            $user = User::where('username', $request->username)->first();
            if (!$user) {
                return $this->error([], 'User not found!');
            }

            $response = [
                'id' => $user->id,
            ];

            return $this->success([
                'user' => $response,
            ], 'User retrieved successfully', 200);
        } catch (\Throwable $th) {
            return $this->error('User not found', 404);
        }
    }


    public function highlight(Request $request)
    {
        $user_id = auth()->user()->id;
        if ($request->user_id) {
            $user_id = $request->user_id;
        }
        $mentions = Mention::where('mentioned_id', $user_id)->get()->pluck('post_id');
        // Get paginated posts
        $posts = $this->posts->whereIn('id', $mentions)
            ->with(['user', 'tags'])
            ->withCount(['likes', 'comments', 'repost'])
            ->with(['bookmarks' => function ($q) use ($user_id) {
                $q->where('user_id', $user_id);
            }])
            ->latest()
            ->get();

        // Add bookmark status
        $posts->transform(function ($post) {
            $post->is_bookmarked = $post->bookmarks->isNotEmpty();
            $post->is_repost = $post->repost->isNotEmpty();
            $post->is_likes = $post->likes->isNotEmpty();
            unset($post->repost);
            unset($post->bookmarks);
            unset($post->likes);
            return $post;
        });
        return $this->success($posts, 'Successfully!', 200);
    }

    public function destroy(Request $request)
    {
        $error = $this->check($request->post_id);

        if ($error) {
            return $error;
        }

        $post = $this->posts->find($request->post_id);

        // Delete all related images from S3
        foreach ($post->images as $image) {
            if ($image->file_url) {
                // Extract S3 relative path if file_url is a full URL
                $path = parse_url($image->file_url, PHP_URL_PATH);
                $path = ltrim($path, '/'); // remove leading slash
                if (Storage::disk('s3')->exists($path)) {
                    Storage::disk('s3')->delete($path);
                }
            }
            $image->delete();
        }

        // Delete related tags
        Tag::where('post_id', $post->id)->delete();

        // Delete related mentions
        Mention::where('post_id', $post->id)->delete();

        $post->delete();

        return $this->success([], 'Post Deleted Successfully!', 200);
    }


    private function check($id)
    {
        $post = $this->posts->find($id);

        if (!$post) {
            return $this->error([], 'Post Not Found!');
        }

        if ($post->user_id != auth()->user()->id) {
            return $this->error([], 'You are not authorized to delete this post.');
        }

        return null;
    }
}
