<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::current(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'active', 'last_login_at']),
            'aiKeyConfigured' => filled(config('hexaqualify.ai.api_key')),
            'aiModel' => config('hexaqualify.ai.model'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'allowed_domains' => ['required', 'array', 'min:1'],
            'allowed_domains.*' => ['required', 'string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'default_expiry_days' => ['required', 'integer', 'min:1', 'max:90'],
            'sender_name' => ['required', 'string', 'max:100'],
            'reply_to' => ['required', 'email'],
            'distribution_list' => ['array'],
            'distribution_list.*' => ['email'],
            'retention_years' => ['required', 'integer', 'min:1', 'max:10'],
            'ai_scoring' => ['boolean'],
        ]);
        $data['allowed_domains'] = array_values(array_unique(array_map('strtolower', $data['allowed_domains'])));
        $before = Setting::current();
        Setting::put($data);
        AuditLog::record('settings.update', 'settings', null, $before, Setting::current());

        return back()->with('success', 'Settings saved.');
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);
        $data['email'] = strtolower($data['email']);
        $domain = substr(strrchr($data['email'], '@'), 1);
        if (! in_array($domain, Setting::get('allowed_domains'), true)) {
            return back()->withErrors(['email' => 'The email domain is not in the allowed staff domains.']);
        }
        $user = User::create($data + ['active' => true]);
        AuditLog::record('user.create', 'user', $user->id, null, $data);

        return back()->with('success', "{$user->name} added as {$user->role}.");
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
            'active' => ['required', 'boolean'],
        ]);
        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access.']);
        }
        $before = $user->only(['role', 'active']);
        $user->update($data);
        AuditLog::record('user.update', 'user', $user->id, $before, $data);

        return back()->with('success', 'User updated.');
    }
}
