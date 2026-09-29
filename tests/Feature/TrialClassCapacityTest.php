<?php

namespace Tests\Feature;

use App\Models\TrialClass;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrialClassCapacityTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidValues(): array
    {
        return [
            'capacity above four' => ['capacity', 5],
            'capacity below four' => ['capacity', 3],
            'count above capacity' => ['confirmed_count', 5],
            'negative count' => ['confirmed_count', -1],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_database_rejects_invalid_class_inserts(string $column, int $value): void
    {
        $this->expectException(QueryException::class);

        DB::table('trial_classes')->insert(array_replace([
            'title' => 'Science', 'subject' => 'Science', 'starts_at' => now()->addDay(),
            'capacity' => 4, 'confirmed_count' => 0,
        ], [$column => $value]));
    }

    #[DataProvider('invalidValues')]
    public function test_database_rejects_invalid_class_updates(string $column, int $value): void
    {
        $trialClass = TrialClass::query()->create([
            'title' => 'Science', 'subject' => 'Science', 'starts_at' => now()->addDay(),
            'capacity' => 4, 'confirmed_count' => 0,
        ]);
        $this->expectException(QueryException::class);

        DB::table('trial_classes')->where('id', $trialClass->id)->update([$column => $value]);
    }
}
