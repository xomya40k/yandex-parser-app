<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\UserDTO;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function login(LoginDTO $dto): UserDTO
    {
        if (!Auth::guard('web')->attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        request()->session()->regenerate();

        $user = $this->userRepository->findById((int) Auth::guard('web')->id());

        if (is_null($user)) {
            throw new RuntimeException('Could not find an authenticated user.');
        }

        return new UserDTO($user);
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        $request = request();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();
    }
}
