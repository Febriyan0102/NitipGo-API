<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by'
    ];

    protected $casts = [
        'value' => 'string'
    ];

    //relation
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }
}