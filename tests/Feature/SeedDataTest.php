<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParentProfile;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TrialClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_provides_all_demo_cases_with_no_duplicate_children(): void
    {
        $this->seed();

        $this->assertDatabaseCount('parents', 8);
        $this->assertDatabaseCount('students', 8);
        $this->assertDatabaseCount('trial_classes', 2);
        $this->assertDatabaseCount('bookings', 6);
        $this->assertDatabaseCount('payment_attempts', 6);
        $science = TrialClass::query()->where('subject', 'Science')->firstOrFail();
        $math = TrialClass::query()->where('subject', 'Mathematics')->firstOrFail();
        $this->assertSame(4, $science->capacity);
        $this->assertSame(3, $science->confirmed_count);
        $this->assertSame(4, $math->capacity);
        $this->assertSame(2, $math->confirmed_count);

        $ida = Student::query()->where('first_name', 'Ida')->firstOrFail();
        $this->postJson('/bookings', ['student_id' => $ida->id, 'trial_class_id' => $math->id])->assertConflict();
        $maya = Student::query()->where('first_name', 'Maya')->firstOrFail();
        $this->assertDatabaseHas('bookings', ['student_id' => $maya->id, 'status' => Booking::PAYMENT_FAILED]);
        $this->getJson("/admin/trial-classes/{$math->id}/roster")->assertJsonCount(2, 'roster');
    }

    public function test_reseeding_preserves_bookings_payments_counters_and_class_dates(): void
    {
        $this->seed();
        $science = TrialClass::query()->where('subject', 'Science')->firstOrFail();
        $eli = Student::query()->where('first_name', 'Eli')->firstOrFail();
        $bookingId = $this->postJson('/bookings', ['student_id' => $eli->id, 'trial_class_id' => $science->id])
            ->assertCreated()->json('booking.id');
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])->assertOk();
        $before = $this->snapshot();
        $this->travel(1)->days();

        $this->seed();

        $this->assertSame($before, $this->snapshot());
        $this->getJson("/admin/trial-classes/{$science->id}/roster")
            ->assertJsonPath('trial_class.confirmed_count', 4)->assertJsonCount(4, 'roster');
        $mira = Student::query()->where('first_name', 'Mira')->firstOrFail();
        $this->postJson('/bookings', ['student_id' => $mira->id, 'trial_class_id' => $science->id])
            ->assertUnprocessable();
    }

    private function snapshot(): array
    {
        return collect([ParentProfile::class, Student::class, TrialClass::class, Booking::class, PaymentAttempt::class])
            ->mapWithKeys(fn (string $model) => [$model => $model::query()->orderBy('id')->get()->toArray()])->all();
    }
}
