<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    public function bookAppointment(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'patient') {
            return response()->json(['message' => 'Only patients can book appointments'], 403);
        }

        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if doctor exists and is a doctor
        $doctor = User::where('id', $request->doctor_id)->where('role', 'doctor')->first();
        if (!$doctor) {
            return response()->json(['message' => 'Invalid doctor selected'], 400);
        }

        // Check for duplicate appointment
        $existing = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'This time slot is already booked'], 409);
        }

        $appointment = Appointment::create([
            'patient_id' => $user->id,
            'doctor_id' => $request->doctor_id,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Appointment booked successfully',
            'appointment' => $appointment,
        ], 201);
    }

    public function getPatientAppointments(Request $request)
    {
        $user = $request->user();

        $appointments = Appointment::where('patient_id', $user->id)
            ->with(['doctor', 'doctor.doctor.specialty'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        return response()->json($appointments);
    }

    public function cancelAppointment(Request $request, $appointmentId)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($appointmentId);

        if ($user->id !== $appointment->patient_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($appointment->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel a completed appointment'], 400);
        }

        $appointment->status = 'cancelled';
        $appointment->save();

        return response()->json([
            'message' => 'Appointment cancelled successfully',
            'appointment' => $appointment,
        ]);
    }
}