<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Support\UuidBin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineBatch extends Model
{
    protected $table = 'medicine_batches';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'id' => BinaryUuid::class,
        'medicine_id' => BinaryUuid::class,
        'pharmacy_location_id' => BinaryUuid::class,
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'quantity' => 'decimal:3',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->getAttributes()['id'] ?? null)) {
                $model->setAttribute('id', UuidBin::generate());
            }
        });
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}