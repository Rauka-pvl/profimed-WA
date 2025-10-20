<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserRequests
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $user = Auth::user();
        $userInfo = $user ? "{$user->name} (ID: {$user->id})" : 'Гость';

        Log::channel('user_actions')->info('🔍 Запрос', [
            'user' => $userInfo,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
