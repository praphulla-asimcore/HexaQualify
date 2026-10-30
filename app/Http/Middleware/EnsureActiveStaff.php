<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out staff who were deactivated after logging in. */
class EnsureActiveStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', 'Your account is inactive.');
        }

        return $next($request);
    }
}
