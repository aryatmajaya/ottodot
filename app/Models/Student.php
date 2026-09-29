<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'first_name', 'last_name', 'date_of_birth'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
