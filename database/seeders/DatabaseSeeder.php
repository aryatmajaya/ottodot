<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Student;
use App\Models\TrialClass;
use App\Services\TrialBookingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([TrialClassSeeder::class, StudentSeeder::class]);

            $math = TrialClass::query()->where('subject', 'Mathematics')->firstOrFail();
            $science = TrialClass::query()->where('subject', 'Science')->firstOrFail();

            if (! $science->bookings()->exists()) {
                foreach (['sophia.chen@example.test', 'priya.patel@example.test', 'daniel.brooks@example.test'] as $email) {
                    $this->seedBooking($science, $email, true);
                }
            }

            if (! $math->bookings()->exists()) {
                $this->seedBooking($math, 'thomas.morris@example.test', true);
                $this->seedBooking($math, 'lena.foster@example.test', true);
                $this->seedBooking($math, 'samira.wilson@example.test', false);
            }
        });
    }

    private function seedBooking(TrialClass $trialClass, string $parentEmail, bool $approved): void
    {
        $student = Student::query()
            ->whereHas('parent', fn ($query) => $query->where('email', $parentEmail))
            ->firstOrFail();

        if (Booking::query()->where('student_id', $student->id)->where('trial_class_id', $trialClass->id)->exists()) {
            return;
        }

        $service = app(TrialBookingService::class);
        $booking = $service->startBooking($student->id, $trialClass->id);
        $service->recordPayment($booking, $approved);
    }
}
