<?php

use App\Models\Booking;
use App\Services\TrialBookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = json_decode(getenv('OTTODOT_TEST_DATABASE'), true, flags: JSON_THROW_ON_ERROR);
if (! str_starts_with($config['database'], 'testing')) {
    throw new RuntimeException('The worker requires a dedicated testing database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql' => $config]);
DB::purge('mysql');

$booking = app(TrialBookingService::class)->recordPayment(Booking::query()->findOrFail((int) $argv[1]), true);
echo json_encode(['id' => $booking->id, 'status' => $booking->status], JSON_THROW_ON_ERROR);
