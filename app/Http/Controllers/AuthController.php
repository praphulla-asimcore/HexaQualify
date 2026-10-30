<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

/** Staff sign-in with Google Workspace (OIDC). MFA is enforced by the IdP. */
class AuthController extends Controller
{
    public function login()
    {
        return Inertia::render('Auth/Login', [
            'devLogin' => config('hexaqualify.dev_login') && ! app()->isProduction(),
            'googleConfigured' => filled(config('services.google.client_id')),
        ]);
    }

    public function redirect()
    {
        $domains = Setting::get('allowed_domains');

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(count($domains) === 1 ? ['hd' => $domains[0], 'prompt' => 'select_account'] : ['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = strtolower((string) $google->getEmail());
        $verified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        return $this->signIn($request, $email, $verified, $google->getName(), $google->getAvatar());
    }

    /** Local development only: sign in as an existing user without Google. */
    public function devLogin(Request $request)
    {
        abort_unless(config('hexaqualify.dev_login') && ! app()->isProduction(), 404);
        $request->validate(['email' => ['required', 'email']]);

        return $this->signIn($request, strtolower($request->input('email')), true, null, null);
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', 'user', $request->user()?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function signIn(Request $request, string $email, bool $verified, ?string $name, ?string $avatar)
    {
        $domain = substr(strrchr($email, '@') ?: '', 1);
        $allowed = array_map('strtolower', Setting::get('allowed_domains'));

        if (! $verified || ! in_array($domain, $allowed, true)) {
            AuditLog::record('login.denied', 'user', null, null, ['email' => $email, 'reason' => 'domain'], 'system');

            return redirect()->route('login')->with('error', 'Please sign in with your Hexamatics Google account.');
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (! $user || ! $user->active) {
            AuditLog::record('login.denied', 'user', $user?->id, null, ['email' => $email, 'reason' => $user ? 'inactive' : 'unknown'], 'system');

            return redirect()->route('login')->with('error', 'Your account has not been set up in HexaQualify. Ask an administrator to add you.');
        }

        $user->fill(array_filter(['name' => $name, 'avatar_url' => $avatar]) + ['last_login_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('login', 'user', $user->id);

        return redirect()->intended(route('dashboard'));
    }
}
