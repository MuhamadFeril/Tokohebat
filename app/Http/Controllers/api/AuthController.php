<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Repositories\AuthRepository;


class AuthController extends Controller
{
    protected $authRepository;

    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->authRepository->register($request->validated());

        return ResponsHelper::success($user, 'Register berhasil', 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $this->authRepository->login($request->validated());

        if (!$login) {
            return ResponsHelper::error('Email atau password salah', 401);
        }

        return ResponsHelper::success($login, 'Login berhasil');
    }

    public function logout()
    {
        $this->authRepository->logout();

        return ResponsHelper::success([], 'Logout berhasil');
    }
}