<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrialClass extends Model
{
    use HasFactory;

    public const CAPACITY = 4;

    protected $fillable = ['title', 'subject', 'starts_at', 'capacity', 'confirmed_count'];

    protected $appends = ['available_seats'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getAvailableSeatsAttribute(): int
    {
        return max(0, min(self::CAPACITY, $this->capacity) - $this->confirmed_count);
    }
}
