@extends('backend.app')

@section('title', 'Create Permissions')

@section('content')
    <main class="app-content content">
        <h2 class="section-title">Create Permissions</h2>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="card">
                        {{-- <div class="card-header">
                            <h4 class="text-end">
                                <a href="javascript:history.back()" class="btn btn-danger float-end">Back</a>
                            </h4>
                        </div> --}}
                        <div class="card-body">
                            <form action="{{ url('permissions') }}" method="post">
                                @csrf
                                <div class="mb-3">
                                    <label for="name" class="form-label">Premissions Name</label>
                                    <input type="text" class="form-control" id="name" name="name">
                                </div>
                                <div class="mb-3">
                                    <button disabled type="submit" class="btn btn-success">Submit</button>

                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
@endsection
