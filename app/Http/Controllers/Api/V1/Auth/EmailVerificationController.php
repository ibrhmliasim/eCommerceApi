<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;

use Illuminate\Http\JsonResponse;
use App\Models\User;

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
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            throw new AuthorizationException();
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * 再送の概要
     * @param Request $request
     * @return JsonResponse
     */
    public function resend(Request $request): JsonResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email verification sent']);
    }
}
