<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Evidence upload (phase 2). */
class ResponseFile extends Model
{
    use HasUuids;

    protected $fillable = ['response_id', 'question_id', 'storage_key', 'filename', 'mime', 'size', 'sha256', 'av_status'];
}
