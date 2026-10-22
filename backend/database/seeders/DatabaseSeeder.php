<?php

namespace Database\Seeders;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@school.test'],
            [
                'name' => 'School Admin',
                'password' => Hash::make('password123'),
            ],
        );

        $admin->assignRole('admin');

        $student = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'login_id' => 'ADM0001',
                'password' => Hash::make('password'),
            ],
        );

        $student->assignRole('student');

        // Student accounts ship with their profile row, the same way the
        // admin's student registration action creates one.
        StudentProfile::firstOrCreate(
            ['user_id' => $student->id],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'status' => 'active',
            ],
        );

        $this->call(DemoDataSeeder::class);
    }
}
