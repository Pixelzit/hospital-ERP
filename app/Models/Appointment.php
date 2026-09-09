<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    protected $table = 'appointments';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'id' => BinaryUuid::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = static::generateBinaryUuid();
            }
        });
    }

    public static function generateBinaryUuid(): string
    {
        return hex2bin(str_replace('-', '', Str::uuid()->toString()));
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}