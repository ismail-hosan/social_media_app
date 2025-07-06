@extends('backend.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="app-content content ">
        <div class="content-overlay"></div>
        <div class="header-navbar-shadow"></div>
        <div class="content-wrapper">
            <div class="">
                <div class="order-1 col-lg-12 col-md-12">
                    <div class="row">
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/profile-avatar.png') }}"
                                                alt="user" width="78px" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">Total User</span>
                                    <h3 class="mb-2 card-title">{{ $total_user ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/profile-avatar.png') }}"
                                                alt="User Card" width="78px" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Active User
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $active_user ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/profile-avatar.png') }}"
                                                alt="User Card" width="78px" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Inactive User
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $inactive_user ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/profile-avatar.png') }}"
                                                alt="User Card" width="78px" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Verifyed User
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $verify_user ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="order-1 col-lg-12 col-md-12">
                    <div class="row">
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/post.png') }}" alt="Credit Card"
                                                height="90px" width="90px" class="" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">Total Post</span>
                                    <h3 class="mb-2 card-title">{{ $total_post ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/channel.png') }}" alt="Credit Card"
                                                height="90px" width="90px" class="" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Total Channel
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $channel ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/post.png') }}" alt="Credit Card"
                                                height="90px" width="90px" class="" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Total Longs Video
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $total_longs ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 col-lg-3 col-md-3 col-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="flex-shrink-0 avatar">
                                            <img src="{{ asset('backend/app-assets/images/post.png') }}" alt="Credit Card"
                                                height="90px" width="90px" class="" />
                                        </div>

                                    </div>
                                    <span class="mb-1 fw-semibold d-block">
                                        Videos View
                                    </span>
                                    <h3 class="mb-2 card-title">{{ $total_video_view ?? 0 }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
