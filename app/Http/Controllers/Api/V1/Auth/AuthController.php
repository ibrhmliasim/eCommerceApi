<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;

class AuthController extends Controller
{
    // コンストラクター(Dependency Injection)を通じてサービスを実装します。
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $user = $this->authService->login($request->only('email', 'password'));

        return UserResource::make($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}