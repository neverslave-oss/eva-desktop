<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);
        return $row ? $row->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function has(string $key): bool
    {
        return static::query()->whereKey($key)->exists();
    }

    /**
     * Whether the setup wizard has been completed for the current app version.
     *
     * Only the explicit `setup_completed` marker counts as complete. Legacy
     * installs that finished setup before the versioned marker existed (no
     * recorded `setup_completed_version`) are treated as complete so existing
     * users are not forced through the wizard again; a fresh install or an app
     * upgrade to a different version is NOT complete so the wizard re-runs.
     */
    public static function isSetupComplete(): bool
    {
        $setupCompleted = filter_var(static::get('setup_completed', false), FILTER_VALIDATE_BOOLEAN);

        if (! $setupCompleted) {
            return false;
        }

        $completedVersion = static::get('setup_completed_version');

        if (empty($completedVersion)) {
            return true;
        }

        return $completedVersion === config('nativephp.version');
    }

    public static function many(array $keys): array
    {
        return static::whereIn('key', $keys)->pluck('value', 'key')->all();
    }
}
