@extends('backend.app')

@section('content')
    <div class="app-content content">
        <div class="container mt-4">
            <!-- Post Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Post Details</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Post Image -->
                        <div class="col-md-4">
                            <img src="{{ asset($post->file_url) }}" class="img-fluid rounded border" alt="Post Image">
                        </div>

                        <!-- Post Info -->
                        <div class="col-md-8">
                            <h3 class="mb-3">{{ $post->title }}</h3>
                            <p class="text-muted">{{ $post->description }}</p>

                            <p class="mt-3">
                                <strong>Status:</strong>
                                @if ($post->status == 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Details Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">User Details</h4>
                </div>
                <div class="card-body">
                    @if ($post->user)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> {{ $post->user->name }}</p>
                                <p><strong>Email:</strong> {{ $post->user->email }}</p>
                                <p><strong>Joined:</strong> {{ $post->user->created_at->format('d M Y') }}</p>
                            </div>
                            <div class="col-md-6">
                                <!-- Optional: Add a user profile picture -->
                                @if ($post->user->profile_image)
                                    <img src="{{ asset($post->user->profile_image) }}" class="img-thumbnail rounded-circle"
                                        alt="User Image" width="120">
                                @else
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($post->user->name) }}&background=random&size=120"
                                        class="img-thumbnail rounded-circle" alt="User Avatar">
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-danger">User information not available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
