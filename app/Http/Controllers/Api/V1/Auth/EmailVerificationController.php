<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Access\AuthorizationException;


class EmailVerificationController extends Controller
{
    /**
     * 検証の概要
     * @param mixed $id
     * @param mixed $hash
     * @throws AuthorizationException
     * @return JsonResponse
     */
    public function verify($id, $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException();
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('auth.email_already_verified')]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => __('auth.email_verified')]);
    }

    /**
     * 再送の概要
     * @param Request $request
     * @return JsonResponse
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('auth.email_already_verified')]);
        }
    
        $user->sendEmailVerificationNotification();
    
        return response()->json(['message' => __('auth.verification_sent')]);
    }
}
