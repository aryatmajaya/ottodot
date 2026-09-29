<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Exceptions\ClassFullException;
use App\Exceptions\InvalidBookingStateException;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TrialClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrialBookingService
{
    /**
     * Creates exactly one payment-pending booking for a child and class.
     * A class row lock serializes duplicate submissions for the same class.
     */
    public function startBooking(int $studentId, int $trialClassId): Booking
    {
        Student::query()->findOrFail($studentId);

        return DB::transaction(function () use ($studentId, $trialClassId): Booking {
            $trialClass = TrialClass::query()->lockForUpdate()->findOrFail($trialClassId);

            $existing = Booking::query()
                ->where('student_id', $studentId)
                ->where('trial_class_id', $trialClass->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === Booking::CONFIRMED) {
                throw new BookingConflictException('This child already has a confirmed booking for this class.');
            }

            if ($existing?->status === Booking::PENDING_PAYMENT) {
                return $existing;
            }

            if ($trialClass->available_seats === 0) {
                throw new ClassFullException('This trial class has no remaining seats.');
            }

            if ($existing) {
                $existing->update([
                    'status' => Booking::PENDING_PAYMENT,
                    'failure_reason' => null,
                ]);

                return $existing->fresh();
            }

            return Booking::query()->create([
                'student_id' => $studentId,
                'trial_class_id' => $trialClass->id,
                'status' => Booking::PENDING_PAYMENT,
            ]);
        });
    }

    /**
     * Records a mock gateway result and claims a seat only after an approval.
     *
     * The guarded increment is the final capacity authority. Its WHERE clause
     * makes only one competing transaction capable of changing 3/4 to 4/4.
     */
    public function recordPayment(Booking $booking, bool $approved): Booking
    {
        // Read the immutable class foreign key before the transaction so every
        // write path locks in the same order: trial class, then booking.
        $trialClassId = $booking->trial_class_id;

        $result = DB::transaction(function () use ($booking, $approved, $trialClassId): Booking {
            $trialClass = TrialClass::query()->lockForUpdate()->findOrFail($trialClassId);
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status === Booking::CONFIRMED) {
                return $booking;
            }

            if ($booking->status !== Booking::PENDING_PAYMENT) {
                throw new InvalidBookingStateException('This booking is no longer awaiting payment.');
            }

            if (! $approved) {
                $this->createPaymentAttempt($booking, PaymentAttempt::FAILED, 'mock_payment_declined');

                $booking->update([
                    'status' => Booking::PAYMENT_FAILED,
                    'failure_reason' => 'mock_payment_declined',
                ]);

                return $booking;
            }

            $seatClaimed = TrialClass::query()
                ->whereKey($trialClass->id)
                ->whereColumn('confirmed_count', '<', 'capacity')
                ->where('confirmed_count', '<', TrialClass::CAPACITY)
                ->increment('confirmed_count');

            if ($seatClaimed === 0) {
                // A real gateway integration would authorize first and void this
                // authorization (or issue a refund) before reporting cancellation.
                $this->createPaymentAttempt($booking, PaymentAttempt::VOIDED, 'seat_unavailable');

                $booking->update([
                    'status' => Booking::CANCELLED,
                    'failure_reason' => 'seat_unavailable',
                ]);

                return $booking;
            }

            $this->createPaymentAttempt($booking, PaymentAttempt::APPROVED);

            $booking->update([
                'status' => Booking::CONFIRMED,
                'seat_claimed_at' => now(),
                'failure_reason' => null,
            ]);

            return $booking;
        });

        return $result->fresh(['student.parent', 'trialClass', 'paymentAttempts']);
    }

    private function createPaymentAttempt(Booking $booking, string $outcome, ?string $failureCode = null): PaymentAttempt
    {
        return $booking->paymentAttempts()->create([
            'provider_transaction_id' => 'mock_'.Str::uuid(),
            'outcome' => $outcome,
            'amount_cents' => 0,
            'failure_code' => $failureCode,
            'metadata' => ['provider' => 'mock'],
            'processed_at' => now(),
        ]);
    }
}
