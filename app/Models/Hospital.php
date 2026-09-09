<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Support\UuidBin;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    protected $table = 'hospitals';

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
                $model->id = UuidBin::generate();
            }
        });
    }
}
