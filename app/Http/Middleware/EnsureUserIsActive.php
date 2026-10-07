<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json(['message' => 'บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้'], 403);
            }

            return redirect()->route('login')->with('error', 'บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้');
        }

        return $next($request);
    }
}
