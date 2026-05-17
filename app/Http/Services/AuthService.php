<?php

namespace App\Services;

use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * ユーザー登録.
     *
     * @return User
     */
    public function register(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        // データベースがクラッシュした場合、サーバーがクラッシュした場合、またはアドレス検証ルールが失敗した場合
        return DB::transaction(function () use ($data) {

            $user = User::create($data);

            if (isset($data['address'])) {
                $address = $user->addresses()->create(
                    array_merge($data['address'], ['type' => 'shipping'])
                );

                $user->update(['default_shipping_address_id' => $address->id]);
            }

            return $user;
        });
        // このブロック内の少なくとも1行が失敗した場合、Laravelは自動的にロールバックされ、データベースにゴミは残りまん。
    }

    /**
     * ユーザー認証.
     *
     * @throws ValidationException
     * @return User
     */
    public function login(array $credentials): User
    {
        $user = User::withTrashed()
            ->where('email', $credentials['email'])
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->trashed()) {
            throw ValidationException::withMessages([
                'email' => 'Account deleted',
            ]);
        }

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        request()->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (Hash::needsRehash($user->password)) {
            $user->update([
                'password' => Hash::make($credentials['password']),
            ]);
        }

        return $user;
    }

    /**
     * ログアウト
     * @return void
     */
    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}