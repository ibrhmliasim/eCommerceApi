<?php
//Won't Fix / By Design - EmailVerificationController、メール検証のためのサービスレイヤーを追加しないように、厚みのある構造になります。AuthServiceはログイン/登録プロセス専用です。

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Access\AuthorizationException;


class EmailVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $user = User::find($request->query('id'));

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->query('hash'))) {
            throw new AuthorizationException(__('auth.invalid_verification_link'));
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('auth.email_already_verified')], 409);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => __('auth.email_verified')]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('auth.email_already_verified')], 409);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('auth.verification_sent')]);
    }
}
