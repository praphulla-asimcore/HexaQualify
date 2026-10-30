<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Admin settings (§4.8), stored as key/value JSON with config defaults. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function defaults(): array
    {
        return [
            'allowed_domains' => config('hexaqualify.allowed_domains'),
            'default_expiry_days' => config('hexaqualify.default_expiry_days'),
            'sender_name' => config('hexaqualify.sender_name'),
            'reply_to' => config('hexaqualify.reply_to'),
            'distribution_list' => [],
            'retention_years' => config('hexaqualify.retention_years'),
            'ai_scoring' => config('hexaqualify.ai.enabled'),
        ];
    }

    public static function current(): array
    {
        $stored = Cache::remember('hq.settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        return array_replace(static::defaults(), array_intersect_key($stored, static::defaults()));
    }

    public static function get(string $key): mixed
    {
        return static::current()[$key] ?? null;
    }

    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, static::defaults()) as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('hq.settings');
    }
}
