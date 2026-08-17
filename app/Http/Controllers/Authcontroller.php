<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Admin login — returns a Sanctum token to send as
     * Authorization: Bearer {token} on future requests.
     * Rate limited to 5/minute per IP (see routes/api.php).
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'role'),
            'token' => $token,
        ]);
    }

    /**
     * POST /api/logout
     * Revokes the token used to make this request.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * GET /api/me
     * Returns the currently authenticated admin (for frontend session checks).
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}