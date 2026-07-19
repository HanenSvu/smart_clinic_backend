<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a specialty for doctor
        Specialty::create(['name' => 'أطفال']);
    }

    public function test_patient_can_book_appointment()
    {
        $patient = User::factory()->patient()->create();
        $doctorUser = User::factory()->doctor()->create();
        
        // Create doctor record
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => 1,
            'consultation_fee' => 150.00,
        ]);

        $response = $this->actingAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $doctorUser->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_duplicate_appointment_fails()
    {
        $patient = User::factory()->patient()->create();
        $doctorUser = User::factory()->doctor()->create();
        
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => 1,
            'consultation_fee' => 150.00,
        ]);

        // Create first appointment
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'pending',
        ]);

        // Try to book same slot
        $response = $this->actingAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $doctorUser->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_doctor_can_update_appointment_status()
    {
        $patient = User::factory()->patient()->create();
        $doctorUser = User::factory()->doctor()->create();
        
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => 1,
            'consultation_fee' => 150.00,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($doctorUser)->putJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_doctor_can_add_notes()
    {
        $patient = User::factory()->patient()->create();
        $doctorUser = User::factory()->doctor()->create();
        
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => 1,
            'consultation_fee' => 150.00,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($doctorUser)->postJson("/api/appointments/{$appointment->id}/notes", [
            'doctor_notes' => 'Patient has mild fever. Prescribed medication.',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}