<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API route'ları için JSON response döndür (redirect yapma)
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Web route'ları için login sayfasına yönlendir (eğer route tanımlıysa)
        return route('login', [], false);
    }
}

