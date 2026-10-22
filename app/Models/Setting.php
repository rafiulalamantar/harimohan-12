<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    /** Defaults used until an admin saves the welcome page settings. */
    public const DEFAULTS = [
        'hero_title' => 'See you again at our beloved school! 🎉',
        'hero_subtitle' => 'Ex-students of every batch: register, find old friends in the alumni directory, and follow notices on the portal.',
        'hero_banner' => null,
    ];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * All settings keyed by name, with defaults filled in.
     *
     * @return array{hero_title: string, hero_subtitle: string, hero_banner: ?string}
     */
    public static function values(): array
    {
        return static::query()->pluck('value', 'key')->all() + self::DEFAULTS;
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public static function bannerUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
