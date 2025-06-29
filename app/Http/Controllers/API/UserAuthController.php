<?php

namespace App\Http\Controllers\API;

use App\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OtpNotification;
use App\Traits\apiresponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserAuthController extends Controller
{
    use apiresponse;

    // public function __construct()
    // {
    //     $this->middleware('auth:api', ['except' => ['login']]);
    // }
    /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|numeric|unique:users',
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:100', // 👈 Add this line
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $validated = $request->only([
                'email',
                'password',
                'phone',
                'birthday',
                'name',
                'country',
            ]);

            $validated['username'] = $this->generateUniqueUsername($validated['name']);
            $validated['password'] = bcrypt($validated['password']);
            $validated['otp'] = $this->generateOtp();

            $user = User::create($validated);
            // $user->notify(new OtpNotification($validated['otp']));

            DB::commit();
            return $this->success(
                $user->only('id', 'username', 'email', 'phone', 'name'),
                'User created successfully. Please check your email for verification.',
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error([], $e->getMessage(), 400);
        }
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6'
        ]);

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->error([], 'Invalid credentials.', 401);
            }
        } catch (JWTException $e) {
            return $this->error([], 'Could not create token.', 500);
        }

        $user = auth()->user();

        if (is_null($user->email_verified_at)) {
            return $this->error([], 'Please verify your email address.', 401);
        }

        if ($user->status === 'inactive') {
            return $this->error([], 'Your account is inactive. Please contact the administrator.', 401);
        }

        return $this->success([
            'token' => $token,
        ], 'User logged in successfully.', 200);
    }


    /**
     * Google Login
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }
        $credentials = $request->only('email');
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
            ]);
        }
        $token = JWTAuth::fromUser($user);
        return $this->success([
            'token' => $this->respondWithToken($token),
        ], 'User logged in successfully.', 200);
    }




    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->error([], 'User not found', 404);
        }

        $otp = $this->generateOtp();
        $user->notify(new OtpNotification($otp));
        $user->otp = $otp;
        $user->is_varified = false;
        $user->save();

        return $this->success(['otp', $otp], 'Check Your Email for Password Reset Otp', 200);
    }

    /**
     * Reset Password Controller
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->first();

        // Check if the OTP is valid
        if (!$user->otp || !$request->otp == $user->otp) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 400);
        }


        // Proceed to reset the password
        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->is_varified = true;
        $user->save();
        $token = JWTAuth::fromUser($user);
        return $this->success($token, 'Password reset successfully.', 200);
    }

    // Resend Otp
    public function resendOtp(Request $request)
    {
        // Validate the email
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error([], 'User not found', 404);
        }

        // Generate and save new OTP
        $otp = $this->generateOtp();
        $user->otp = $otp;
        $user->save();

        // Send OTP notification
        // $user->notify(new OtpNotification($otp));

        return $this->success(['otp' => $otp], 'Check your email for the password reset OTP.', 200);
    }


    /**
     * Varify User Otp
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);
        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // // Check if OTP is null or expired
        // if (!$user->otp || !$user->otp_expiry || Carbon::now()->greaterThan($user->otp_expiry)) {
        //     return $this->error([], 'OTP has expired or is not set.', 400);
        // }

        // Check if OTP matches
        if ($user->otp != $request->otp) {
            return $this->error([], 'Invalid OTP.', 400);
        }

        // $user->is_varified = true;
        // $user->otp = null;
        // $user->save();

        // $token = JWTAuth::fromUser($user);

        return $this->success([], 'OTP verified successfully', 200);
    }

    public function information()
    {
        $user = auth()->user();

        return $this->success($user, 'Data Fetch Successfully!', 200);
    }


    public function registerCheckOTP(Request $request)
    {
        // Validate the request inputs
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->all(), 422); // Returning all validation errors
        }

        // Check if the user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error([], 'User not found', 404); // If user doesn't exist
        }

        // Verify the OTP before updating the email_verified_at
        if ($user->otp !== $request->otp) {
            return $this->error([], 'Invalid OTP', 400); // OTP mismatch
        }

        // Mark the email as verified and reset OTP
        $user->email_verified_at = now();
        $user->otp = null;
        $user->save();

        // Generate a new JWT token
        $token = JWTAuth::fromUser($user); // Use fromUser to create the token directly from the user instance

        return $this->success($token, 'OTP validated successfully. Your account is now verified.', 200);
    }


    /**
     * Log out the user (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Get token from the request
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->error([], 'Token not provided', 400);
            }

            JWTAuth::invalidate($token);

            return $this->success([], 'Successfully logged out', 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->error([], 'Token is already invalidated', 400);
        } catch (\Exception $e) {
            return $this->error([], 'Could not log out user', 500);
        }
    }


    /**
     * Get the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profileMe(Request $request)
    {
        try {
            $authUser = auth()->user();
            $user = $authUser;

            // If a different user_id is passed and it's not the current user
            if ($request->filled('user_id') && $request->user_id != $authUser->id) {
                $user = User::find($request->user_id);

                if (!$user) {
                    return $this->error('User not found', 404);
                }
            }

            // Get list of users this user has liked
            $likedUsers = $user->likes()
                ->with('likeable.socalMedia') // Eager load socalMedia from the likeable (User)
                ->get()
                ->filter(function ($like) {
                    return $like->likeable instanceof \App\Models\User;
                })
                ->map(function ($like) {
                    return [
                        'id' => $like->likeable->id,
                        'name' => $like->likeable->name,
                        'avatar' => $like->likeable->avatar,
                        // 'social_media' => $like->likeable->socalMedia ?? null,
                    ];
                });

            $response = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'cover_image' => $user->cover_image ?? null,
                'username' => $user->username,
                'bio' => $user->bio,
                'joined' => 'Joined ' . $user->created_at->format('M Y'),
                'liked_users' => $likedUsers,
                'media' => $user->socalMedia
            ];

            return $this->success([
                'user' => $response,
            ], 'User retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Something went wrong: ' . $e->getMessage(), 500);
        }
    }




    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            // Refresh the token
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return $this->success([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ], 'Token refreshed successfully', 200);
        } catch (\Exception $e) {
            return $this->error([], $e->getMessage(), 400);
        }
    }

    /**
     * Get Token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function guard()
    {
        return Auth::guard();
    }

    private function generateUniqueUsername($name)
    {
        $base = strtolower(preg_replace('/\s+/', '', $name)); // remove spaces and lowercase
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }
}
