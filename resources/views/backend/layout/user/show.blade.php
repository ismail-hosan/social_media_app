@extends('backend.app')
@section('title', 'General Setting')
@section('content')
    <div class="app-content content ">
        <div class="container mt-5">
            <!-- User Information Section -->
            <div class="card card-body mb-2">
                <div class="row">
                    <div class="col-md-4">
                        <!-- Avatar -->
                        <img src="{{ asset($user->avatar) ?? '' }}" alt="User Avatar" class="img-fluid rounded-circle"
                            width="150">
                    </div>
                    <div class="col-md-4">
                        <!-- User Details -->
                        <h3>{{ $user->name ?? 'No Name' }}</h3>
                        <p><strong>Username:</strong> {{ $user->username ?? 'N/A' }}</p>
                        <p><strong>Email:</strong> {{ $user->email }}</p>
                        <p><strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}</p>
                        <p><strong>Address:</strong> {{ $user->address ?? 'N/A' }}</p>
                        <p><strong>Joined:</strong> {{ $user->created_at->format('d M Y') }}</p>
                        <p><strong>Status:</strong>
                            @if ($user->status == 'active')
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h3>Bio</h3>
                        @php
                            $decodedBio = json_decode($user->bio, true); // decode as associative array
                        @endphp

                        @foreach ($decodedBio as $key => $value)
                            <p><strong>{{ ucfirst($key) }}:</strong> {{ $value }}</p>
                        @endforeach
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <button onclick="toggleVerification({{ $user->id }}, {{ $user->base ? 0 : 1 }})"
                            class="btn btn-sm {{ $user->base ? 'btn-danger' : 'btn-success' }}">
                            {{ $user->base ? 'Unverify' : 'Verify' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script>
        async function toggleVerification(userId, newStatus) {
            const actionText = newStatus === 1 ? 'verify' : 'unverify';

            const {
                value: password
            } = await Swal.fire({
                icon: 'question',
                title: `Are you sure you want to ${actionText} this user?`,
                input: 'password',
                inputLabel: 'Enter your admin password to confirm',
                inputPlaceholder: 'Your password',
                inputAttributes: {
                    maxlength: 100,
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            });

            if (!password) return;

            const formData = new FormData();
            formData.append('id', userId);
            formData.append('verified', newStatus);
            formData.append('password', password);
            formData.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: "{{ route('user.verify') }}", // adjust if your route name is different
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Toast.fire({
                        icon: 'success',
                        title: response.message,
                    });
                    setTimeout(() => location.reload(), 1500);
                },
                error: function(xhr) {
                    Toast.fire({
                        icon: 'error',
                        title: xhr.responseJSON?.message || 'An error occurred.',
                    });
                }
            });
        }
    </script>
@endpush
