<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Foundation\Auth\User as Authenticatable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Notifications\Notifiable;
use App\Notifications\VerifyEmailNotification;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'phone',
        'first_name',
        'last_name',
        'default_shipping_address_id',
        'default_billing_address_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => 'string',
        ];
    }

    // ─── Relationships　リレーション ────────────────────────────────────────

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function defaultShippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'default_shipping_address_id');
    }

    public function defaultBillingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'default_billing_address_id');
    }

    // Notifications
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}