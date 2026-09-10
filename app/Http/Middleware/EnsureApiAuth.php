<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('auth_user_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return $next($request);
    }
}
