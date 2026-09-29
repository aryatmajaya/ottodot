<?php

namespace Database\Seeders;

use App\Models\ParentProfile;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['parent_name' => 'Samira Wilson', 'parent_email' => 'samira.wilson@example.test', 'first_name' => 'Maya', 'last_name' => 'Wilson', 'date_of_birth' => '2016-02-14'],
            ['parent_name' => 'Aisha Rahman', 'parent_email' => 'aisha.rahman@example.test', 'first_name' => 'Mira', 'last_name' => 'Rahman', 'date_of_birth' => '2016-04-12'],
            ['parent_name' => 'Daniel Brooks', 'parent_email' => 'daniel.brooks@example.test', 'first_name' => 'Leo', 'last_name' => 'Brooks', 'date_of_birth' => '2015-09-05'],
            ['parent_name' => 'Sophia Chen', 'parent_email' => 'sophia.chen@example.test', 'first_name' => 'Noah', 'last_name' => 'Chen', 'date_of_birth' => '2017-01-22'],
            ['parent_name' => 'Priya Patel', 'parent_email' => 'priya.patel@example.test', 'first_name' => 'Ava', 'last_name' => 'Patel', 'date_of_birth' => '2014-11-18'],
            ['parent_name' => 'Thomas Morris', 'parent_email' => 'thomas.morris@example.test', 'first_name' => 'Sofia', 'last_name' => 'Morris', 'date_of_birth' => '2016-06-08'],
            ['parent_name' => 'Lena Foster', 'parent_email' => 'lena.foster@example.test', 'first_name' => 'Ida', 'last_name' => 'Foster', 'date_of_birth' => '2015-07-13'],
            ['parent_name' => 'Marcus Rivera', 'parent_email' => 'marcus.rivera@example.test', 'first_name' => 'Eli', 'last_name' => 'Rivera', 'date_of_birth' => '2018-03-09'],
        ];

        foreach ($students as $student) {
            $parent = ParentProfile::query()->firstOrCreate(
                ['email' => $student['parent_email']],
                ['name' => $student['parent_name']]
            );

            Student::query()->firstOrCreate(
                ['parent_id' => $parent->id, 'first_name' => $student['first_name'], 'last_name' => $student['last_name']],
                ['date_of_birth' => $student['date_of_birth']]
            );
        }
    }
}
