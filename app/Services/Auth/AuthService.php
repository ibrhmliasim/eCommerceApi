<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\Auth\InvalidCredentialsException;
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
        $user = DB::transaction(function () use ($dto): User {
            $user = User::create([
                'first_name' => $dto->first_name,
                'last_name'  => $dto->last_name,
                'email'      => $dto->email,
                'password'   => $dto->password,
                'phone'      => $dto->phone,
            ]);

            event(new Registered($user));

            // 自動ログイン: ユーザーはカート/ウィッシュリスト/プロフィールにすぐにアクセスできます。
            // 電子メールの検証を待たずに。チェックアウトは引き続き保護されています
            // ミドルウェアはルーティング レベルで「検証済み」です (ADR-8 を参照)。
            Auth::login($user);

            return $user;
        });

        return $user;
    }

     /**
     * ユーザー認証.
     *
     * セッション再生成はHTTPレイヤー（Controller）の責務のため、ここでは行わない。
     *
     * @throws InvalidCredentialsException
     */
    public function login(LoginDTO $dto): User
    {
        // future: User::withTrashed() -> for recovering deleted accounts

        // ユーザーが存在しない、またはパスワードが一致しない場合は同じエラーを返す。(ユーザー存在の有無を攻撃者に知らせないため)
        if (! Auth::guard('web')->attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw new InvalidCredentialsException();
        }

        /** @var User $user */
        $user = Auth::user();

        // パスワードハッシュのアップグレード (bcryptコスト変更時など)
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $dto->password])->save();
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