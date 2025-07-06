<?php

namespace App\Http\Controllers\web\backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Namu\WireChat\Models\Conversation;
use Namu\WireChat\Models\Group;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Group::with(['conversation.participants'])->latest();

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

                ->editColumn('image', function ($data) {
                    $url = $data->avatar_url ?? null;
                    if ($url) {
                        return '<img src="' . $url . '" alt="Image" width="80" height="80" style="object-fit: cover;">';
                    }
                    return 'No image';
                })

                ->addColumn('participants', function ($data) {
                    $participants = $data->conversation->participants->count();
                    return $participants;
                })

                ->editColumn('type', function ($data) {
                    return $data->type; // Customize if needed
                })

                ->addColumn('action', function ($data) {
                    return '<button type="button" onclick="showDeleteAlert(' . $data->conversation->id . ')" class="btn btn-sm btn-danger">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>';
                })

                ->rawColumns(['bulk_check', 'image', 'participants', 'action'])

                ->make(true);
        }
        return view('backend.layout.group.index');
    }

    public function destroy($id)
    {
        // Retrieve the conversation along with its group
        $conversation = Conversation::with('group')->find($id);

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        DB::beginTransaction();

        try {
            if ($conversation->group) {
                $conversation->group->delete();
            }

            $conversation->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Deleted Successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Deletion failed. Try again!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
