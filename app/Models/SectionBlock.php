<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A reusable section (e.g. the common "Company and integrity" baseline) included by reference. */
class SectionBlock extends Model
{
    use HasUuids;

    public const BASELINE = 'company-integrity';

    protected $fillable = ['slug', 'title', 'questions_json', 'guide_json'];

    protected function casts(): array
    {
        return ['questions_json' => 'array', 'guide_json' => 'array'];
    }
}
