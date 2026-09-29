<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingConflictException;
use App\Exceptions\ClassFullException;
use App\Exceptions\InvalidBookingStateException;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\TrialBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, TrialBookingService $trialBookings): JsonResponse|RedirectResponse
    {
        try {
            $booking = $trialBookings->startBooking(
                $request->integer('student_id'),
                $request->integer('trial_class_id'),
            );
        } catch (BookingConflictException $exception) {
            return $this->error($request, $exception->getMessage(), 409);
        } catch (ClassFullException $exception) {
            return $this->error($request, $exception->getMessage(), 422);
        }

        $booking->load(['student.parent', 'trialClass']);

        if ($request->expectsJson()) {
            return response()->json(['booking' => $booking], 201);
        }

        return to_route('bookings.show', $booking);
    }

    public function show(Booking $booking): View|JsonResponse
    {
        $booking->load(['student.parent', 'trialClass', 'paymentAttempts']);

        if (request()->expectsJson()) {
            return response()->json(['booking' => $booking]);
        }

        return view('bookings.show', compact('booking'));
    }

    public function payment(RecordPaymentRequest $request, Booking $booking, TrialBookingService $trialBookings): JsonResponse|RedirectResponse
    {
        try {
            $booking = $trialBookings->recordPayment($booking, $request->string('result')->toString() === 'succeeded');
        } catch (InvalidBookingStateException $exception) {
            return $this->error($request, $exception->getMessage(), 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['booking' => $booking]);
        }

        return to_route('bookings.show', $booking);
    }

    private function error(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->withInput()->withErrors(['booking' => $message]);
    }
}
