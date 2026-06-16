<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Configuration extends Model
{
    protected $table = 'configurations';

    protected $fillable = ['key', 'value'];

    /**
     * Default values for known configuration keys. Used when the row does not
     * exist yet, so exports keep working out of the box.
     */
    public const DEFAULTS = [
        'school_name'      => 'Ecole Nationale des Sciences Appliquées - Al Hoceima',
        'department_name'  => 'Département Mathématiques et Informatique',
        'school_logo_path' => null,
        'site_name'        => 'PFE.Admin',
        'site_subtitle'    => 'ENSAH',
    ];

    /** Read a configuration value (falls back to the provided/known default). */
    public static function get(string $key, ?string $default = null): ?string
    {
        $rows = Cache::remember('app_configurations', 300, function () {
            return static::query()->pluck('value', 'key')->all();
        });

        return $rows[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** Create or update a configuration value. */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_configurations');
    }

    /** Absolute filesystem path to the uploaded logo, or null if none/missing. */
    public static function logoPath(): ?string
    {
        $rel = self::get('school_logo_path');
        if (! $rel) {
            return null;
        }
        $path = Storage::disk('public')->path($rel);

        return is_file($path) ? $path : null;
    }

    /**
     * Base64 data URI for the uploaded logo, safe to embed in DomPDF (which
     * cannot reach files outside its public/ chroot). Null if no logo.
     */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! $path) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            'svg'        => 'image/svg+xml',
            default      => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /** Public URL to the uploaded logo (for HTML previews), or null. */
    public static function logoUrl(): ?string
    {
        $rel = self::get('school_logo_path');
        if (! $rel || ! Storage::disk('public')->exists($rel)) {
            return null;
        }

        return asset('storage/' . $rel);
    }
}
