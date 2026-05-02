<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'postal_code',
        'prefecture',
        'city',
        'ward',
        'address_line1',
        'address_line2',
    ];

    // ─── Relationships リレーション ────────────────────────────────────────

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}