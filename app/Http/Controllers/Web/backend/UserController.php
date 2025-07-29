<?php

namespace App\Http\Controllers\Web\backend;

use App\Models\User;
use App\Helper\Helper;
use Illuminate\Http\Request;
use App\Services\UserService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Service;
use App\Traits\apiresponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public $userServiceObj;
    use apiresponse;

    public function __construct()
    {
        $this->userServiceObj = new UserService();
    }

    public function create()
    {
        $data['roles'] = Role::all();
        return view('backend.layout.user.create', $data);
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first())->withInput();
        }
        $data = $request->all();
        // Create a new user instance
        $user = new User();
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->username = Helper::generateUniqueUsername($data['name']);
        $user->email = $data['email'];
        // $user->opt = null;
        $user->email_verified_at = now();
        $user->password = Hash::make($data['password']);

        $user->save();

        // $user->assignRole($data['role']);

        // Redirect or return a success message
        return redirect()->back()->with('success', 'User created successfully!');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            // Build query first without executing it
            $query = User::query()->latest();

            // Filter by country
            if ($request->type) {
                $query->where('country', $request->type);
            }

            $data = $query->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $status = $data->status;
                    return '<div class="form-check form-switch mb-2">
                                <input class="form-check-input" onclick="showStatusChangeAlert(' . $data->id . ')" type="checkbox" ' . ($status == 'active' ? 'checked' : '') . '>
                            </div>';
                })
                ->addColumn('bulk_check', function ($data) {
                    return Helper::tableCheckbox($data->id);
                })
                ->addColumn('action', function ($data) {
                    $editButton = '';
                    if (Auth::user()->is_admin) {
                        $editRoute = route('user.edit', ['id' => $data->id]);
                        $editButton = ' <a class="btn btn-sm btn-info" href="' . $editRoute . '">
                                            <i class="fa-solid fa-pencil"></i>
                                        </a>';
                    }

                    $viewButton = '';
                    if (Auth::user()->is_admin) {
                        $viewRoute = route('show.user', ['id' => $data->id]);
                        $viewButton = ' <a class="btn btn-sm btn-primary" href="' . $viewRoute . '">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>';
                    }

                    $deleteButton = '';
                    if (Auth::user()->is_admin) {
                        $deleteButton = '<button type="button" onclick="deleteUser(' . $data->id . ')" class="btn btn-sm btn-danger">
                                            <i class="fa-regular fa-circle-xmark"></i>
                                         </button>';
                    }

                    return '<div>
                                ' . $editButton . '
                                ' . $viewButton . '
                                ' . $deleteButton . '
                            </div>';
                })

                ->rawColumns(['bulk_check', 'status', 'action'])
                ->make(true);
        }

        $countries = User::where('status', 'active')
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country');

        // Get country-wise user counts
        $countryCounts = User::select('country', DB::raw('count(*) as total'))
            ->groupBy('country')
            ->get()
            ->pluck('total', 'country');

        return view('backend.layout.user.index', compact('countries', 'countryCounts'));
    }

    public function edit($id)
    {
        $data['user'] = User::find($id);
        $data['role'] = User::find($id)->role;

        return view('backend.layout.user.edit', $data);
    }

    public function update(Request $request)
    {
        try {
            $user = User::findOrFail($request->id); // Ensure user exists

            $rules = [
                'name'     => 'nullable|string|max:250',
                'email'    => 'nullable|email|unique:users,email,' . $user->id,
                'username' => 'nullable|string|unique:users,username,' . $user->id,
                'phone'    => 'nullable|string|max:15|unique:users,phone,' . $user->id,
                'avatar'   => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            ];

            $validated = $request->validate($rules);

            // Update user data
            $user->update([
                'name'     => $validated['name'] ?? $user->name,
                'username' => $validated['username'] ?? $user->username,
                'email'    => $validated['email'] ?? $user->email,
                'phone'    => $validated['phone'] ?? $user->phone,
            ]);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                if ($user->avatar && file_exists($user->avatar) && $user->avatar != 'default/user.png') {
                    unlink($user->avatar);
                }

                $path = Service::fileUpload($request->file('avatar'), 'profile_pictures/admins/');
                $user->update(['avatar' => $path]);
            }

            return redirect()->back()->with('success', 'Information Updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        return $this->userServiceObj->show($id);
    }

    public function verify(Request $request)
    {
        // Validate admin password
        if (!Hash::check($request->password, Auth::user()->password)) {
            return $this->error([], 'Incorrect Password', 401);
        }

        // Find the user
        $user = User::find($request->id);
        if (!$user) {
            return $this->error([], 'User not found.', 404);
        }

        // Validate and sanitize 'verified' input
        if (!in_array($request->verified, ['0', '1', 0, 1], true)) {
            return $this->error([], 'Invalid verified value.', 422);
        }

        $verified = (int) $request->verified;

        // Update verified status
        $updated = $user->update(['base' => $verified]);

        // Optional: Remove sessions if unverified (you can remove this if not needed)
        if ($verified === 0) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if ($updated) {
            $statusText = $verified === 1 ? 'verified' : 'unverified';
            return $this->success([], "User has been {$statusText}.", 200);
        }

        return $this->error([], 'Verification update failed. Please try again.', 500);
    }


    public function status(int $id): JsonResponse
    {
        $data = User::findOrFail($id);
        if ($data->status == 'active') {
            $data->status = 'inactive';
            $data->save();

            return response()->json([
                'success' => false,
                'message' => 'Unpublished Successfully.',
                'data' => $data,
            ]);
        } else {
            $data->status = 'active';
            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Published Successfully.',
                'data' => $data,
            ]);
        }
    }

    public function destroy(Request $request)
    {
        if (!Hash::check($request->password, Auth::user()->password)) {
            return $this->error([], 'Incorrect Password', 401);
        }

        $user = User::find($request->id);
        $deleted = $user->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        if ($deleted) {
            return $this->success([], 'Account Deleted Successfully', 200);
        }

        return $this->error([], 'Account deletion failed. Please try again.', 500);
    }
}
