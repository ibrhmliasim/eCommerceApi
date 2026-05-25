<?php

namespace App\Services;

use App\Models\User;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;

class AuthService
{
    /**
     * ユーザー登録.
     *
     * @return User
     */
    public function register(RegisterDTO $dto): User
    {
        $user = DB::transaction(function () use ($dto) {
            $user = User::create([
                'first_name'    => $dto->first_name,
                'last_name'     => $dto->last_name,
                'email'         => $dto->email,
                'password'      => Hash::make($dto->password),
            ]);

            if ($dto->address !== null) {
                $address = $user->addresses()->create(
                    array_merge($dto->address)
                );

                $user->update(['default_shipping_address_id' => $address->id]);
            }

            return $user;
        });
        
        event(new Registered($user));

        return $user;
    }

     /**
     * ユーザー認証.
     *
     * セッション再生成はHTTPレイヤー（Controller）の責務のため、ここでは行わない。
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): User
    {
        // future: User::withTrashed() -> for recovering deleted accounts


        // ユーザーが存在しない、またはパスワードが一致しない場合は同じエラーを返す
        // (ユーザー存在の有無を攻撃者に知らせないため)
        if (! Auth::attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // パスワードハッシュのアップグレード (bcryptコスト変更時など)
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($dto->password)])->save();
        }

        return $user;
    }

     /**
     * ログアウト — Authガードのみ担当。セッション破棄はControllerで。
     * @return void
     */
    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}