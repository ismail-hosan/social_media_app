<?php

use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\FilterController;
use App\Http\Controllers\API\FollowController;
use App\Http\Controllers\API\GroupController;
use App\Http\Controllers\API\HobbyController;
use App\Http\Controllers\API\LikeController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\RecentController;
use App\Http\Controllers\API\ReelsController;
use App\Http\Controllers\API\SocalMediaLinkController;
use App\Http\Controllers\API\StoryController;
use App\Http\Controllers\API\StripeController;
use App\Http\Controllers\API\TagsController;
use App\Http\Controllers\API\UserAuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\WishlistController;
use App\Http\Controllers\API\BookmarkController;
use App\Http\Controllers\RepostController;
use App\Models\BlockUser;
use App\Models\User;
use App\Notifications\Notify;
use Illuminate\Support\Facades\Route;

Route::controller(UserAuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('register-verify-otp', 'registerCheckOTP');


    Route::post('logout', 'logout');

    // Resend Otp
    Route::post('resend-otp', 'resendOtp');

    // Forget Password
    Route::post('forget-password', 'forgetPassword');
    Route::post('verify-otp', 'checkOTP');
    Route::post('reset-password', 'resetPassword');

    // Google Login
    Route::post('google/login', 'googleLogin');
});

Route::group(['middleware' => ['jwt.verify', 'user']], function () {
    Route::post('logout', [UserAuthController::class, 'logout']);
    Route::get('profile/me', [UserAuthController::class, 'profileMe']);
    Route::post('refresh', [UserAuthController::class, 'refresh']);
    Route::get('information', [UserAuthController::class, 'information']);

    Route::delete('/delete/user', [UserController::class, 'deleteUser']);

    // Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('profile-update', [UserController::class, 'updateUserInfo']);

    // All post route
    Route::controller(PostController::class)->prefix('post')->group(function () {
        Route::post('store', 'store');
        Route::get('mention', 'mention');
        Route::get('foryou', 'forYou');
        Route::get('get', 'index');
        Route::get('highlight', 'highlight');
        Route::post('delete', 'destroy');
    });

    // All hobby route
    Route::controller(HobbyController::class)->prefix('hobby')->group(function () {
        Route::get('get', 'get');
        Route::post('store', 'store');
    });

    // All comment route
    Route::controller(CommentController::class)->prefix('comment')->group(function () {
        Route::post('store', 'store');
        Route::get('get/{id}', 'index');
        Route::post('/reply/{comment}','reply');
        Route::post('/react/{comment}','react');
    });

    // User Socal Media Link
    Route::controller(SocalMediaLinkController::class)->prefix('socal')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
    });

    // All Repost route
    Route::controller(RepostController::class)->prefix('repost')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
    });

    // All Wishlist route
    Route::controller(WishlistController::class)->prefix('wishlist')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
    });

    // All Likelist route
    Route::controller(LikeController::class)->prefix('like')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
    });

    // All Followers
    Route::controller(FollowController::class)->prefix('follow')->group(function () {
        Route::post('store', 'store');
        Route::get('search', 'search');
        // Route::get('how', 'whoToFollow');
        Route::get('followersPosts', 'followersPosts');
        Route::get('get', 'index');
        Route::post('accept', 'accept');
        Route::get('findfriends', 'findfriends');
    });

    // All Bookmarks
    Route::controller(BookmarkController::class)->prefix('bookmarks')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
    });


    // All Post Tags
    Route::controller(TagsController::class)->prefix('tags')->group(function () {
        Route::post('get', 'index');
        Route::post('suggested', 'suggestedFollwer');
    });

    // All Reels 
    Route::controller(ReelsController::class)->prefix('reels')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
        Route::get('/reels/{slug}', 'showBySlug');
        Route::get('timeline', 'timeline');
        Route::post('count', 'shareCount');
        Route::get('personal/{id}', 'personal');
        Route::post('delete', 'destroy');
    });

    // All story route
    Route::controller(StoryController::class)->prefix('story')->group(function () {
        Route::post('store', 'store');
        Route::get('get', 'index');
        Route::post('mute', 'mute');
        Route::post('block', 'block');
        Route::post('report', 'report');
        Route::get('followers', 'followerStory');
        Route::post('react', 'react');
        Route::get('all/{id}', 'all');
        Route::get('/story/{slug}', 'showBySlug');
        Route::get('/react/show/{id}', 'reactShow');
    });

    // All Chat route
    Route::controller(ChatController::class)->group(function () {
        Route::post('/chat/message', 'sendMessage');
        Route::get('/chat/get/messages', 'getConversations');
        Route::get('/chat/get', 'getConversations');
        Route::get('/chat/user/conversation/{user}', 'getUserConversation');
        Route::post('/chat/search', 'searchUsers');
        Route::post('/chat/create/covesation', 'createCovesation');
        Route::post('/chat/globalSearch', 'globalSearch');
        Route::post('/chat/block', 'covesationBlock');
        Route::post('/chat/message/react', 'messageReact');
        Route::get('/chat/link/conversation/{encryptedId}', 'linkConversation');
        Route::post('/chat/conversation/delete', 'removeCovesation');
    });

    Route::controller(GroupController::class)->prefix('group')->group(function () {
        Route::post('/search', 'search');
        Route::post('/memberadd', 'addGroupMember');
        Route::post('/groupmessage', 'groupMessage');
        Route::post('/request', 'sendRequest');
        Route::post('/request/remove', 'removeRequest');
        Route::get('/get', 'get');
        Route::post('/chat/group/create', 'groupCreate');
        Route::get('/info/{id}', 'info');
        Route::post('/member', 'member');
        Route::post('/leave', 'leave');
        Route::post('/update/information', 'updateInfo');
        Route::post('/linkgenerate', 'generateLink');
    });

    // All Filter 
    Route::controller(FilterController::class)->prefix('search')->group(function () {
        Route::post('get', 'index');
        Route::get('trending', 'trending');
        Route::post('suggest', 'suggest');
    });

    // All recent
    Route::controller(RecentController::class)->prefix('recent')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
        Route::delete('/', 'clearAll');
    });

    Route::controller(StripeController::class)->prefix('payment')->group(function () {
        Route::post('create', 'checkout');
    });

    Route::get('/checkout/success', [StripeController::class, 'successs'])->name('checkout.success');

    // Route for canceled payment
    Route::get('/checkout/cancel', [StripeController::class, 'cancel'])->name('checkout.cancel');



    // Get Notifications
    Route::get('/my-notifications', [UserController::class, 'getMyNotifications']);
    Route::get('send-notification', function () {
        $user = User::where('id', Auth::id())->first();
        $user->notify(new Notify("Test Notification"));

        //Send fire base notification
        // $device_tokens = FirebaseTokens::where(function ($query) {
        //     $query->where('user_id', Auth::id())
        //         ->orWhereNull('user_id');
        // })
        //     ->where('is_active', '1')
        //     ->get();
        // $data = [
        //     'message' => $user->name . ' has sent you a notification',
        // ];
        // foreach ($device_tokens as $device_token) {
        //     Helper::sendNotifyMobile($device_token->token, $data);
        // }

        return $response = ['success' => true, 'message' => 'Notification sent successfully'];
    });
});

Route::post('/save-fcm-token', [NotificationController::class, 'storeFcmToken']);
Route::post('/send-notification', [NotificationController::class, 'sendNotification']);
Route::get('/notifications/{user_id}', [NotificationController::class, 'getUserNotifications']);

Route::post('stripe/webhook', [WebhookController::class, 'handle']);
