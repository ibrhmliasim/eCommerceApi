<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\AuthService;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(
            RegisterDTO::fromRequest($request) // array → 型付きDTO
        );

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            LoginDTO::fromRequest($request) // validated() を DTO 経由で安全に渡す
        );

        // セッション固定攻撃の防止 — HTTPの責務なのでControllerに置く
        $request->session()->regenerate();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        // セッション完全破棄 — CSRF トークンも再生成
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response();
    }
}