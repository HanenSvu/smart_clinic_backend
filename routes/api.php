<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\AppointmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/specialties', [DoctorController::class, 'getSpecialties']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Doctors - Public for all authenticated users
    Route::get('/doctors', [DoctorController::class, 'getAvailableDoctors']);
    Route::get('/doctors/{doctorId}/available-slots', [AppointmentController::class, 'getAvailableTimeSlots']);
    Route::get('/doctors/{doctorId}/statistics', [AppointmentController::class, 'getDoctorStatistics']);
    
    // Doctor specific routes (only for doctors)
    Route::get('/doctor/appointments/{doctorId}', [AppointmentController::class, 'getDoctorAppointments']);
    Route::put('/appointments/{appointmentId}/status', [AppointmentController::class, 'updateAppointmentStatus']);
    Route::post('/appointments/{appointmentId}/notes', [AppointmentController::class, 'addDoctorNotes']);
    
    // Patient specific routes (only for patients)
    Route::post('/appointments/book', [AppointmentController::class, 'bookAppointment']);
    Route::get('/my-appointments', [AppointmentController::class, 'getPatientAppointments']);
    Route::delete('/appointments/{appointmentId}/cancel', [AppointmentController::class, 'cancelAppointment']);
    
    // Common routes
    Route::get('/appointments/{appointmentId}', [AppointmentController::class, 'getAppointmentDetails']);
});

// Test route
Route::get('/health', function () {
    return response()->json(['status' => 'API is working']);
});