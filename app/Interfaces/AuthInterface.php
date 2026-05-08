<?php
namespace App\Interfaces;
Interface AuthInterface
{
    public function login($request);
    public function register($request);
    public function logout();
}