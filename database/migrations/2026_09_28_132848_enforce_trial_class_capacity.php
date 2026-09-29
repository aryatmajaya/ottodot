<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $counts = DB::table('bookings')->where('status', 'confirmed')
            ->selectRaw('trial_class_id, COUNT(*) AS total')
            ->groupBy('trial_class_id')->pluck('total', 'trial_class_id');

        foreach ($counts as $classId => $count) {
            if ($count > 4) {
                throw new RuntimeException("Trial class {$classId} already has {$count} confirmed bookings. Resolve the excess bookings before migrating.");
            }
        }

        foreach (DB::table('trial_classes')->pluck('id') as $classId) {
            DB::table('trial_classes')->where('id', $classId)->update([
                'capacity' => 4,
                'confirmed_count' => $counts[$classId] ?? 0,
            ]);
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("CREATE TRIGGER trial_classes_capacity_{$event}
                    BEFORE {$event} ON trial_classes
                    WHEN NEW.capacity != 4 OR NEW.confirmed_count < 0 OR NEW.confirmed_count > 4
                    BEGIN SELECT RAISE(ABORT, 'Trial classes must have capacity 4 and confirmed_count between 0 and 4'); END");
            }

            return;
        }

        DB::statement('ALTER TABLE trial_classes ADD CONSTRAINT trial_classes_capacity_check CHECK (capacity = 4)');
        DB::statement('ALTER TABLE trial_classes ADD CONSTRAINT trial_classes_count_check CHECK (confirmed_count >= 0 AND confirmed_count <= capacity)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trial_classes_capacity_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trial_classes_capacity_update');

            return;
        }

        DB::statement('ALTER TABLE trial_classes DROP CHECK trial_classes_count_check');
        DB::statement('ALTER TABLE trial_classes DROP CHECK trial_classes_capacity_check');
    }
};
