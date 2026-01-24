<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'annual_leave_entitlement' => 30,
        ]);

        // Create HR users
        User::create([
            'name' => 'HR Manager',
            'email' => 'hr@example.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'annual_leave_entitlement' => 30,
        ]);

        User::create([
            'name' => 'HR Staff',
            'email' => 'hr2@example.com',
            'password' => Hash::make('password'),
            'role' => 'hr',
            'annual_leave_entitlement' => 30,
        ]);

        // Create General users
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'general',
            'annual_leave_entitlement' => 30,
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'role' => 'general',
            'annual_leave_entitlement' => 30,
        ]);

        User::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'role' => 'general',
            'annual_leave_entitlement' => 30,
        ]);

        User::create([
            'name' => 'Alice Williams',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'role' => 'general',
            'annual_leave_entitlement' => 30,
        ]);

        $this->command->info('Users seeded successfully!');
    }
}
