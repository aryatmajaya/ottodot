<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\TrialClassRosterController;
use App\Models\Student;
use App\Models\TrialClass;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'students' => Student::query()->with('parent')->orderBy('first_name')->get(),
        'trialClasses' => TrialClass::query()->orderBy('starts_at')->get(),
    ]);
})->name('booking.create');

Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::post('/bookings/{booking}/payment', [BookingController::class, 'payment'])->name('bookings.payment');

Route::get('/admin/trial-classes/{trialClass}/roster', TrialClassRosterController::class)
    ->name('trial-classes.roster');
Route::get('/admin/trial-classes/{trialClass}/roster/view', [TrialClassRosterController::class, 'view'])
    ->name('trial-classes.roster.view');
