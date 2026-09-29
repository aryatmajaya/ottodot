<?php

namespace Database\Seeders;

use App\Models\TrialClass;
use Illuminate\Database\Seeder;

class TrialClassSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['title' => 'Maths Problem Solvers (ages 8–10)', 'subject' => 'Mathematics', 'days' => 3],
            ['title' => 'Science Lab: Forces (ages 9–11)', 'subject' => 'Science', 'days' => 4],
        ] as $trialClass) {
            TrialClass::query()->firstOrCreate(
                ['title' => $trialClass['title'], 'subject' => $trialClass['subject']],
                [
                    'starts_at' => now()->addDays($trialClass['days'])->setTime(16, 0),
                    'capacity' => TrialClass::CAPACITY,
                    'confirmed_count' => 0,
                ]
            );
        }
    }
}
