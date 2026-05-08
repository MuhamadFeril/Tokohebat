<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthRepository
{
    public function findByEmail(string $email)
    {
        return User::where('email', $email)->first();
    }

    public function register(array $data)
    {
        // hash password
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // create token (Sanctum/Passport personal access token)
        $token = $user->createToken('auth_token')->plainTextToken;
        return $token;
    }

    public function login(array $credentials)
    {
        $user = $this->findByEmail($credentials['email']);

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // return token string
            return $user->createToken('auth_token')->plainTextToken;
        }

        return null;
    }

    public function logout(Request $request = null)
    {
        $user = $request && $request->user() ? $request->user() : Auth::user();

        if ($user) {
            // revoke all tokens (or adjust to only revoke current token)
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
            Auth::logout();
        }

        return true;
    }
}
