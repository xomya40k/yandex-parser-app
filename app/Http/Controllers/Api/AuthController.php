<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Auth\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): UserResource
    {
        $dto = new LoginDTO(
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
        );

        return new UserResource($this->authService->login($dto)->user);
    }

    public function logout(): Response
    {
        $this->authService->logout();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
