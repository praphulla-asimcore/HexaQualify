<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Vendor pages get no staff data at all.
        if ($request->is('v/*')) {
            return [
                'flash' => fn () => ['error' => $request->session()->get('error')],
                'company' => config('hexaqualify.company'),
            ];
        }

        $user = $request->user();

        return [
            'auth' => fn () => $user ? [
                'user' => [
                    'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                    'role' => $user->role, 'avatar_url' => $user->avatar_url,
                ],
            ] : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'link' => $request->session()->get('link'),
            ],
            'company' => config('hexaqualify.company'),
        ];
    }
}
