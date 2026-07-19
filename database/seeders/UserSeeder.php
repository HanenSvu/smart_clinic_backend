<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin doctor
        $doctorUser = User::create([
            'full_name' => 'Dr. Ahmed Mohamed',
            'email' => 'doctor@clinic.com',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
        ]);

        Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => 1,
            'consultation_fee' => 150.00,
        ]);

        // Create patient
        User::create([
            'full_name' => 'Patient Test',
            'email' => 'patient@test.com',
            'password' => Hash::make('password123'),
            'role' => 'patient',
        ]);
    }
}