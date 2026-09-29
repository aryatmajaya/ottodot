<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const PENDING_PAYMENT = 'pending_payment';
    public const CONFIRMED = 'confirmed';
    public const PAYMENT_FAILED = 'payment_failed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'student_id',
        'trial_class_id',
        'status',
        'seat_claimed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return ['seat_claimed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function trialClass(): BelongsTo
    {
        return $this->belongsTo(TrialClass::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
