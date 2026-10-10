<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\UserProfile;
use App\Models\PendingEmailChange;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;

class UsersProfileSettingsController extends Controller
{

    // Pending Email
    public function requestEmailChange(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();

            $otp = random_int(100000, 999999);

            PendingEmailChange::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'new_email' => $request->email,
                    'otp_hash' => Hash::make($otp),
                    'expires_at' => now()->addMinutes(10),
                ]
            );

            Mail::to($request->email)
                ->send(new SendOtpMail($otp));

            return response()->json([
                'message' => 'Verification OTP sent',
                'otp' => $otp, // REMOVE IN PRODUCTION
            ]);
        }

// Verify Email requestEmailChange
public function verifyEmailChange(Request $request)
{
    $validator = Validator::make($request->all(), [
        'otp' => 'required|string|size:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = $request->user();

    $pending = PendingEmailChange::where(
        'user_id',
        $user->id
    )->first();

    if (!$pending) {
        return response()->json([
            'message' => 'Pending email change not found',
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

    $user->update([
        'email' => $pending->new_email,
        'email_verified_at' => now(),
    ]);

    PendingEmailChange::where(
    'new_email',
            $pending->new_email
        )->delete();

    
    return response()->json([
        'message' => 'Email updated successfully',
        'user' => $user->fresh()->load([
                    'profile',
                    'pendingEmailChange',
]),
    ]);
}

// Update Email request
    public function update(Request $request): JsonResponse
{
    $user = $request->user();

    $validator = Validator::make($request->all(), [
        'name' => 'nullable|string|max:255',

        'email' => [
            'nullable',
            'email',
            'max:255',
        ],

        'organization_name' => 'nullable|string|max:255',

        'phone' => 'nullable|string|max:30',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    /*
    |--------------------------------------------------------------------------
    | UPDATE NORMAL USER DATA
    |--------------------------------------------------------------------------
    */

    if (isset($validated['name'])) {

        $user->update([
            'name' => $validated['name'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    $user->profile()->updateOrCreate(
        [
            'user_id' => $user->id,
        ],
        [
            'organization_name' =>
                $validated['organization_name'] ?? null,

            'phone' =>
                $validated['phone'] ?? null,

            'status' => 'active',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | EMAIL CHANGE DETECTION
    |--------------------------------------------------------------------------
    */

    $emailChanged = false;

    if (
        isset($validated['email']) &&
        $validated['email'] !== $user->email
    ) {

        $emailChanged = true;

        $request->merge([
            'email' => $validated['email'],
        ]);

        $this->requestEmailChange($request);
    }

    return response()->json([

        'message' => $emailChanged
            ? 'Profile updated. Verify your new email.'
            : 'Profile updated successfully.',

        'requires_email_verification' => $emailChanged,

        'user' => $user->fresh()->load([
                'profile',
                'pendingEmailChange',
            ]),
    ]);
}

// Resend Email Request
public function resendEmailChangeOtp(Request $request)
{
    $user = $request->user();

    $pending = PendingEmailChange::where(
        'user_id',
        $user->id
    )->first();

    if (!$pending) {
        return response()->json([
            'message' => 'Pending email change not found',
        ], 404);
    }

    $otp = random_int(100000, 999999);

    $pending->update([
        'otp_hash' => Hash::make($otp),
        'expires_at' => now()->addMinutes(10),
    ]);

    Mail::to($pending->new_email)
        ->send(new SendOtpMail($otp));

    return response()->json([
        'message' => 'OTP resent successfully',
        'otp' => $otp, // REMOVE IN PRODUCTION
    ]);
}
}