<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    public function getAvailableDoctors(Request $request)
    {
        $specialtyId = $request->query('specialty_id');
        $query = Doctor::with(['user', 'specialty']);

        if ($specialtyId) {
            $query->where('specialty_id', $specialtyId);
        }

        $doctors = $query->get();

        return response()->json($doctors);
    }

    public function getDoctorAppointments(Request $request, $doctorId)
    {
        $user = $request->user();
        
        if ($user->role !== 'doctor' && $user->id != $doctorId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $date = $request->query('date');
        $query = Appointment::where('doctor_id', $doctorId)
            ->with(['patient', 'doctor']);

        if ($date) {
            $query->whereDate('appointment_date', $date);
        }

        $appointments = $query->orderBy('appointment_date')->orderBy('appointment_time')->get();

        return response()->json($appointments);
    }

    public function updateAppointmentStatus(Request $request, $appointmentId)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($appointmentId);

        if ($user->id !== $appointment->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointment->status = $request->status;
        $appointment->save();

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'appointment' => $appointment,
        ]);
    }

    public function addDoctorNotes(Request $request, $appointmentId)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($appointmentId);

        if ($user->id !== $appointment->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'doctor_notes' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointment->doctor_notes = $request->doctor_notes;
        $appointment->status = 'completed';
        $appointment->save();

        return response()->json([
            'message' => 'Doctor notes added successfully',
            'appointment' => $appointment,
        ]);
    }

    public function getSpecialties()
    {
        $specialties = \App\Models\Specialty::all();
        return response()->json($specialties);
    }
}