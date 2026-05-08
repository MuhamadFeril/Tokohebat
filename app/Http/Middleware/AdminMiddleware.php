<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);

        }

        if ($request->user()->role !== 'admin') {

            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);

        }

        return $next($request);
    }
}