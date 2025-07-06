<?php

namespace App\Http\Controllers\Web\backend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class PostController extends Controller
{
    public function index(Request $request)
    {

        if ($request->ajax()) {
            $data = Post::latest();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('bulk_check', function ($data) {
                    return '<div class="form-checkbox">
                                <input type="checkbox" class="form-check-input select_data"
                                       id="checkbox-' . $data->id . '"
                                       value="' . $data->id . '"
                                       onClick="select_single_item(' . $data->id . ')">
                                <label class="form-check-label" for="checkbox-' . $data->id . '"></label>
                            </div>';
                })
                ->editColumn('username', function ($data) {
                    return $data->user->username ?? '';
                })
                ->editColumn('image', function ($data) {
                    $url = $data->file_url ?? null;

                    if ($url) {
                        return '<img src="' . $url . '" alt="Image" width="80" height="80" style="object-fit: cover;">';
                    }

                    return 'No images';
                })
                ->editColumn('status', function ($data) {
                    return '<div class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input"
                            onclick="changeStatus(event,' . $data->id . ')"
                            ' . ($data->status == "active" ? "checked" : "") . '></div>';
                })
                ->addColumn('action', function ($data) {
                    $viewRoute = route('post.show', ['id' => $data->id]);
                    return ' <a class="btn btn-sm btn-primary" href="' . $viewRoute . '">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                            <button type="button" onclick="showDeleteAlert(' . $data->id . ')" class="btn btn-sm btn-danger">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>';
                })
                ->rawColumns(['bulk_check', 'image', 'status', 'action','username'])
                ->make(true);
        }
        return view('backend.layout.post.index');
    }

    public function show($id)
    {
        $post = Post::with('user')->find($id);
        return view('backend.layout.post.show', compact('post'));
    }

    public function destroy($id)
    {
        $item = Post::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        // Delete the file if it exists
        if ($item->file_url && file_exists(public_path($item->file_url))) {
            unlink(public_path($item->file_url));
        }

        $delete = $item->delete();

        if ($delete) {
            return response()->json(['success' => true, 'message' => 'Deleted Successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Try Again!']);
        }
    }
}
