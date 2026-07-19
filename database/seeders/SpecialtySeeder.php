<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialty;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = ['أطفال', 'قلبية', 'أسنان', 'جلدية', 'عظام', 'نفسية', 'نسائية'];
        
        foreach ($specialties as $specialty) {
            Specialty::create(['name' => $specialty]);
        }
    }
}