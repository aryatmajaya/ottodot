<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParentProfile;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TrialClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_child_cannot_have_two_confirmed_bookings_for_the_same_class(): void
    {
        $student = $this->student('Mia');
        $trialClass = $this->trialClass();
        $bookingId = $this->startBooking($student, $trialClass);

        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CONFIRMED);

        $this->postJson('/bookings', ['student_id' => $student->id, 'trial_class_id' => $trialClass->id])
            ->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => Booking::CONFIRMED]);
        $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 1]);
    }

    public function test_a_failed_payment_never_claims_a_roster_seat(): void
    {
        $student = $this->student('Mia');
        $trialClass = $this->trialClass();
        $bookingId = $this->startBooking($student, $trialClass);

        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'failed'])
            ->assertOk()->assertJsonPath('booking.status', Booking::PAYMENT_FAILED);

        $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 0]);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId, 'status' => Booking::CONFIRMED]);
        $this->assertDatabaseHas('payment_attempts', ['booking_id' => $bookingId, 'outcome' => PaymentAttempt::FAILED]);
    }

    public function test_only_one_payment_can_claim_the_last_seat(): void
    {
        $trialClass = $this->trialClass(confirmedCount: 3);
        $firstStudent = $this->student('Alice');
        $secondStudent = $this->student('Ben');
        $firstBookingId = $this->startBooking($firstStudent, $trialClass);
        $secondBookingId = $this->startBooking($secondStudent, $trialClass);

        // User B completes first after both users reached mock payment.
        $this->postJson("/bookings/{$secondBookingId}/payment", ['result' => 'succeeded'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CONFIRMED);
        // User A's success result arrives after the class becomes 4/4.
        $this->postJson("/bookings/{$firstBookingId}/payment", ['result' => 'succeeded'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CANCELLED)
            ->assertJsonPath('booking.failure_reason', 'seat_unavailable');

        $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 4]);
        $this->assertSame(1, Booking::query()->where('trial_class_id', $trialClass->id)
            ->whereIn('student_id', [$firstStudent->id, $secondStudent->id])
            ->where('status', Booking::CONFIRMED)->count());
        $this->assertDatabaseHas('payment_attempts', [
            'booking_id' => $firstBookingId,
            'outcome' => PaymentAttempt::VOIDED,
            'failure_code' => 'seat_unavailable',
        ]);
    }

    public function test_the_roster_endpoint_returns_confirmed_students_only(): void
    {
        $trialClass = $this->trialClass();
        $confirmed = $this->student('Mia');
        $failed = $this->student('Noah');
        $confirmedBooking = Booking::query()->create([
            'student_id' => $confirmed->id, 'trial_class_id' => $trialClass->id,
            'status' => Booking::CONFIRMED, 'seat_claimed_at' => now(),
        ]);
        $trialClass->increment('confirmed_count');
        Booking::query()->create([
            'student_id' => $failed->id, 'trial_class_id' => $trialClass->id,
            'status' => Booking::PAYMENT_FAILED,
        ]);

        $this->getJson("/admin/trial-classes/{$trialClass->id}/roster")
            ->assertOk()->assertJsonPath('trial_class.confirmed_count', 1)
            ->assertJsonCount(1, 'roster')->assertJsonPath('roster.0.booking_id', $confirmedBooking->id)
            ->assertJsonPath('roster.0.student', 'Mia Example');
    }

    public function test_repeated_pending_submission_and_payment_do_not_duplicate_records(): void
    {
        $student = $this->student('Mia');
        $trialClass = $this->trialClass();
        $bookingId = $this->startBooking($student, $trialClass);

        $this->assertSame($bookingId, $this->startBooking($student, $trialClass));
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])->assertOk();
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CONFIRMED);
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'failed'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CONFIRMED);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 1]);
    }

    public function test_payment_failure_can_be_retried_without_losing_attempt_history(): void
    {
        $student = $this->student('Mia');
        $trialClass = $this->trialClass();
        $bookingId = $this->startBooking($student, $trialClass);
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'failed'])->assertOk();
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])->assertConflict();

        $this->assertSame($bookingId, $this->startBooking($student, $trialClass));
        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'succeeded'])
            ->assertOk()->assertJsonPath('booking.status', Booking::CONFIRMED);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseHas('payment_attempts', ['booking_id' => $bookingId, 'outcome' => PaymentAttempt::FAILED]);
        $this->assertDatabaseHas('payment_attempts', ['booking_id' => $bookingId, 'outcome' => PaymentAttempt::APPROVED]);
        $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 1]);
    }

    public function test_a_full_class_rejects_new_bookings(): void
    {
        $trialClass = $this->trialClass(confirmedCount: 4);
        $student = $this->student('Mia');

        $this->postJson('/bookings', ['student_id' => $student->id, 'trial_class_id' => $trialClass->id])
            ->assertUnprocessable()->assertJsonPath('message', 'This trial class has no remaining seats.');

        $this->assertDatabaseMissing('bookings', ['student_id' => $student->id]);
        $this->getJson("/admin/trial-classes/{$trialClass->id}/roster")->assertJsonCount(4, 'roster');
    }

    public function test_invalid_payment_result_leaves_the_booking_pending(): void
    {
        $bookingId = $this->startBooking($this->student('Mia'), $this->trialClass());

        $this->postJson("/bookings/{$bookingId}/payment", ['result' => 'anything'])
            ->assertUnprocessable()->assertJsonValidationErrors('result');

        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => Booking::PENDING_PAYMENT]);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_parent_can_complete_the_html_flow_and_view_the_roster(): void
    {
        $student = $this->student('Mia');
        $trialClass = $this->trialClass();
        $this->get('/')->assertOk()->assertSee('Mia Example');

        $response = $this->post('/bookings', ['student_id' => $student->id, 'trial_class_id' => $trialClass->id]);
        $booking = Booking::query()->sole();
        $response->assertRedirect(route('bookings.show', $booking));
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('pending payment');
        $this->post(route('bookings.payment', $booking), ['result' => 'succeeded'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Your child is on the confirmed roster.');
        $this->get("/admin/trial-classes/{$trialClass->id}/roster/view")->assertOk()->assertSee('Mia Example');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => Booking::CONFIRMED]);
    }

    private function startBooking(Student $student, TrialClass $trialClass): int
    {
        return $this->postJson('/bookings', ['student_id' => $student->id, 'trial_class_id' => $trialClass->id])
            ->assertCreated()->json('booking.id');
    }

    private function student(string $firstName): Student
    {
        $parent = ParentProfile::query()->create([
            'name' => "{$firstName}'s parent",
            'email' => strtolower($firstName).'.'.uniqid().'@example.test',
        ]);

        return $parent->students()->create(['first_name' => $firstName, 'last_name' => 'Example']);
    }

    private function trialClass(int $confirmedCount = 0): TrialClass
    {
        $trialClass = TrialClass::query()->create([
            'title' => 'Science Lab', 'subject' => 'Science', 'starts_at' => now()->addDay(),
            'capacity' => 4, 'confirmed_count' => 0,
        ]);

        for ($index = 1; $index <= $confirmedCount; $index++) {
            $student = $this->student("Existing{$index}");
            Booking::query()->create([
                'student_id' => $student->id,
                'trial_class_id' => $trialClass->id,
                'status' => Booking::CONFIRMED,
                'seat_claimed_at' => now(),
            ]);
            $trialClass->increment('confirmed_count');
        }

        return $trialClass->fresh();
    }
}
