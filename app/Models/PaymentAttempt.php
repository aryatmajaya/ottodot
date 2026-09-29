<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    public const APPROVED = 'approved';
    public const FAILED = 'failed';
    public const VOIDED = 'voided';

    protected $fillable = [
        'booking_id',
        'provider_transaction_id',
        'outcome',
        'amount_cents',
        'failure_code',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'processed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
