<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParentProfile;
use App\Models\PaymentAttempt;
use App\Models\TrialClass;
use App\Services\TrialBookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlLastSeatRaceTest extends TestCase
{
    public function test_two_blocked_mysql_payments_compete_for_one_remaining_seat(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run with DB_CONNECTION=mysql and a dedicated testing database.');
        }

        $database = DB::connection()->getDatabaseName();
        $this->assertStringStartsWith('testing', $database, 'Use a dedicated database named testing or testing_*.');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $parent = ParentProfile::query()->create([
            'name' => 'Race test parent', 'email' => Str::uuid().'@example.test',
        ]);
        $trialClass = TrialClass::query()->create([
            'title' => 'Concurrent science trial', 'subject' => 'Science',
            'starts_at' => now()->addDay(), 'capacity' => 4, 'confirmed_count' => 0,
        ]);
        $workers = [];

        try {
            $service = app(TrialBookingService::class);
            $contenders = [];
            for ($index = 0; $index < 5; $index++) {
                $student = $parent->students()->create(['first_name' => "Child {$index}", 'last_name' => 'Race']);
                $booking = $service->startBooking($student->id, $trialClass->id);
                if ($index < 3) {
                    $service->recordPayment($booking, true);
                } else {
                    $contenders[] = $booking;
                }
            }

            // Hold the class lock until both independent connections are visibly
            // executing their locking SELECT. This proves actual lock contention.
            DB::beginTransaction();
            TrialClass::query()->lockForUpdate()->findOrFail($trialClass->id);
            foreach ($contenders as $booking) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/record-payment.php'), (string) $booking->id], base_path(), [
                    'APP_ENV' => 'testing',
                    'OTTODOT_TEST_DATABASE' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR),
                ], timeout: 20);
                $worker->start();
                $workers[] = $worker;
            }

            $deadline = microtime(true) + 10;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(
                    fn (object $thread): bool => $thread->db === $database
                        && str_contains(strtolower($thread->Info ?? ''), 'trial_classes')
                        && str_contains(strtolower($thread->Info ?? ''), 'for update')
                )->count();
                if ($waiting === 2) {
                    break;
                }
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            $this->assertSame(2, $waiting, 'Both payments must contend for the held class lock.');
            DB::commit();
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput().$worker->getOutput());
            }

            $statuses = Booking::query()->whereKey(array_map(fn (Booking $booking) => $booking->id, $contenders))
                ->orderBy('status')->pluck('status')->all();
            $this->assertSame([Booking::CANCELLED, Booking::CONFIRMED], $statuses);
            $this->assertDatabaseHas('trial_classes', ['id' => $trialClass->id, 'confirmed_count' => 4]);
            $this->getJson("/admin/trial-classes/{$trialClass->id}/roster")
                ->assertOk()->assertJsonCount(4, 'roster');
            $loser = Booking::query()->where('trial_class_id', $trialClass->id)->where('status', Booking::CANCELLED)->sole();
            $this->assertDatabaseHas('payment_attempts', [
                'booking_id' => $loser->id, 'outcome' => PaymentAttempt::VOIDED, 'failure_code' => 'seat_unavailable',
            ]);
            $this->assertSame(5, PaymentAttempt::query()->whereIn('booking_id', $trialClass->bookings()->select('id'))->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop(0);
            }
            $trialClass->bookings()->delete();
            $trialClass->delete();
            $parent->students()->delete();
            $parent->delete();
        }
    }
}
