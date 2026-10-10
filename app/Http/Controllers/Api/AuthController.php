<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use App\Models\EmailOtp;
use App\Models\PendingRegistration;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;

class AuthController extends Controller
{

    private function generateOTP($email, $name, $password)
    {
        $otp = random_int(100000, 999999);

        PendingRegistration::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'name' => $name,
                'password' => $password,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
            ]
        );

        Mail::to($email)
            ->send(new SendOtpMail($otp));

        return $otp;
    }


        // Resend OTP
    public function resendOtp(Request $request): JsonResponse
        {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $pending = PendingRegistration::where(
                'email',
                $request->email
            )->first();

            if (!$pending) {
                return response()->json([
                    'message' => 'Pending registration not found',
                ], 404);
            }

            $otp = $this->generateOTP(
                $pending->email,
                $pending->name,
                $pending->password
            );

            return response()->json([
                'message' => 'OTP resent successfully',
                'otp' => $otp, // REMOVE IN PRODUCTION
            ]);
    }


    // Register a user and persist profile details in a separate table.
   public function register(Request $request): JsonResponse
        {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $otp = $this->generateOTP(
                $validated['email'],
                $validated['name'],
                Hash::make($validated['password'])
            );

            return response()->json([
                'message' => 'OTP sent successfully',
                'otp' => $otp, // REMOVE IN PRODUCTION
            ], 200);
        }

    // Verify Otp Service
        public function verifyOtp(Request $request): JsonResponse
            {
                $validator = Validator::make($request->all(), [
                    'email' => 'required|email',
                    'otp' => 'required|string|size:6',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $pending = PendingRegistration::where(
                    'email',
                    $request->email
                )->first();

                if (!$pending) {
                    return response()->json([
                        'message' => 'Pending registration not found',
                    ], 404);
                }

                if ($pending->expires_at->isPast()) {
                    return response()->json([
                        'message' => 'OTP expired',
                    ], 422);
                }

                if (!Hash::check($request->otp, $pending->otp_hash)) {
                    return response()->json([
                        'message' => 'Invalid OTP',
                    ], 422);
                }

                $user = User::create([
                    'name' => $pending->name,
                    'email' => $pending->email,
                    'password' => $pending->password,
                    'email_verified_at' => now(),
                ]);

                // Create a default user profile
                $user->profile()->create([
                    'role' => 'farmer',
                    'status' => 'active',
                ]);

                $pending->delete();

                return response()->json([
                    'message' => 'OTP verified successfully',
                    'user' => $user->load('profile'),
                ]);
            }
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $request->session()->regenerate();

        $user = $request->user();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load('profile'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}
