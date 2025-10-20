<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogHelper
{
    public static function userAction(string $action, array $details = [])
    {
        $user = Auth::user();
        $userInfo = $user
            ? "{$user->name} (ID: {$user->id}, Email: {$user->email})"
            : 'Гость';

        $context = array_merge([
            'user' => $userInfo,
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ], $details);

        Log::channel('user_actions')->info("🧾 Действие: {$action}", $context);
    }
}
