<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids;

    public const ROLES = ['admin', 'reviewer', 'viewer'];

    protected $fillable = ['email', 'name', 'role', 'active', 'avatar_url', 'last_login_at'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->active && in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** Admins and reviewers may see scoring guides and act on responses. */
    public function canReview(): bool
    {
        return $this->hasRole('admin', 'reviewer');
    }
}
