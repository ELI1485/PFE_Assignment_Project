<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuration extends Model
{
    protected $table = 'configurations';

    protected $fillable = ['cle', 'valeur'];

    /**
     * Default values for known configuration keys. Used when the row does not
     * exist yet, so exports keep working out of the box.
     */
    public const DEFAULTS = [
        'etablissement' => 'Ecole Nationale des Sciences Appliquées - Al Hoceima',
        'departement'   => 'Département Mathématiques et Informatique',
        'session'       => 'Première Session',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $rows = Cache::remember('app_configurations', 300, function () {
            return static::query()->pluck('valeur', 'cle')->all();
        });

        return $rows[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['cle' => $key], ['valeur' => $value]);
        Cache::forget('app_configurations');
    }
}
