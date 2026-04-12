<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Credenciais inválidas.', 401);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role->value,
            ],
        ], 'Login realizado com sucesso.');
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return $this->success(message: 'Logout realizado com sucesso.');
    }

    public function me(): JsonResponse
    {
        $user = request()->user();

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role->value,
            'last_login_at' => $user->last_login_at,
        ]);
    }
}
