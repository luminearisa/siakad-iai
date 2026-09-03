<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Settings\Models\Setting;

class SettingService
{
    protected const CACHE_PREFIX = 'siakad_setting_';
    protected const CACHE_TTL = 86400; // 24 hours

    /**
     * Get a setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(self::CACHE_PREFIX . $key, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            if (!$setting) {
                return $default;
            }
            return $setting->typed_value;
        });
    }

    /**
     * Set/update a setting value.
     */
    public function set(
        string $key,
        mixed $value,
        ?string $type = null,
        ?string $group = null,
        ?string $description = null
    ): Setting {
        $setting = Setting::firstOrNew(['key' => $key]);

        if ($type) {
            $setting->type = $type;
        }

        $setting->setTypedValue($value);

        if ($group) {
            $setting->group = $group;
        }

        if ($description) {
            $setting->description = $description;
        }

        $setting->save();

        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * Get all settings.
     */
    public function all(): Collection
    {
        return Setting::all();
    }

    /**
     * Get settings grouped by group.
     */
    public function getByGroup(string $group): Collection
    {
        return Setting::where('group', $group)->get();
    }

    /**
     * Batch update settings from key => value array.
     */
    public function batchUpdate(array $settings): Collection
    {
        $updated = collect();

        foreach ($settings as $key => $val) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $setting->setTypedValue($val);
                $setting->save();
                Cache::forget(self::CACHE_PREFIX . $key);
                $updated->push($setting);
            }
        }

        return $updated;
    }
}
