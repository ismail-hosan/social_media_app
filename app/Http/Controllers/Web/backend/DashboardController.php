<?php

namespace App\Http\Controllers\Web\backend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
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
        $user = User::all();
        $total_user = $user->count();
        $active_user = $user->where('status', 'active')->count();
        $inactive_user = $user->where('status', 'inactive')->count();
        $verify_user = $user->whereNotNull('base')->count();

        $post = Post::all();
        $total_post = $post->count();

        $channel = Conversation::where('type','group')->count();
        return view('backend.dashboard', get_defined_vars());
    }
}
