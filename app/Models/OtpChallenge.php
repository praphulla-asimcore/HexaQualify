<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    use HasUuids;

    protected $fillable = ['invitation_id', 'code_hash', 'expires_at', 'attempts', 'locked_until', 'verified_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'locked_until' => 'datetime', 'verified_at' => 'datetime'];
    }
}
