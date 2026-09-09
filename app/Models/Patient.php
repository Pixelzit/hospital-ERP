<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Support\UuidBin;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patients';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => BinaryUuid::class,
        'hospital_id' => BinaryUuid::class,
        'branch_id' => BinaryUuid::class,
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

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }
}
