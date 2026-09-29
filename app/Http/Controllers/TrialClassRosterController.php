<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\TrialClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TrialClassRosterController extends Controller
{
    public function __invoke(TrialClass $trialClass): JsonResponse
    {
        return response()->json($this->payload($trialClass));
    }

    public function view(TrialClass $trialClass): View
    {
        return view('admin.roster', [
            'trialClass' => $trialClass,
            'roster' => $this->rosterRows($trialClass),
        ]);
    }

    private function payload(TrialClass $trialClass): array
    {
        return [
            'trial_class' => [
                'id' => $trialClass->id,
                'title' => $trialClass->title,
                'starts_at' => $trialClass->starts_at->toIso8601String(),
                'capacity' => $trialClass->capacity,
                'confirmed_count' => $trialClass->confirmed_count,
                'available_seats' => $trialClass->available_seats,
            ],
            'roster' => $this->rosterRows($trialClass),
        ];
    }

    private function rosterRows(TrialClass $trialClass): Collection
    {
        return $trialClass->bookings()
            ->where('status', Booking::CONFIRMED)
            ->with('student.parent')
            ->orderBy('seat_claimed_at')
            ->get()
            ->map(fn (Booking $booking) => [
                'booking_id' => $booking->id,
                'student' => $booking->student->full_name,
                'parent' => $booking->student->parent->name,
                'confirmed_at' => $booking->seat_claimed_at?->toIso8601String(),
            ]);
    }
}
