<?php

namespace App\Http\Controllers\Web\backend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Namu\WireChat\Models\Conversation;

class DashboardController extends Controller
{
    /**
     * Display Admin Panel
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        // User stats using DB queries (not collection)
        $total_user = User::count();
        $active_user = User::where('status', 'active')->count();
        $inactive_user = User::where('status', 'inactive')->count();
        $verify_user = User::whereNotNull('base')->count();

        // Post stats
        $total_post = Post::count();
        $today_post = Post::whereDate('created_at', Carbon::today())->count();

        // Channel stats (group conversations)
        $channelQuery = Conversation::where('type', 'group');
        $total_channel = (clone $channelQuery)->count();
        $channel_today = (clone $channelQuery)->whereDate('created_at', Carbon::today())->count();

        // Return data to the view
        return view('backend.dashboard', compact(
            'total_user',
            'active_user',
            'inactive_user',
            'verify_user',
            'total_post',
            'today_post',
            'total_channel',
            'channel_today'
        ));
    }
}
