<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionnaire extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['slug', 'name', 'category', 'description', 'active', 'sort_order', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionnaireVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        // Ordered hasOne rather than ofMany(): Postgres has no max() for uuid keys.
        return $this->hasOne(QuestionnaireVersion::class)->orderByDesc('version');
    }
}
