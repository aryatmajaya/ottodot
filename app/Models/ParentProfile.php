<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentProfile extends Model
{
    use HasFactory;

    protected $table = 'parents';

    protected $fillable = ['name', 'email'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_id');
    }
}
