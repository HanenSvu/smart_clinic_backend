<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * حجز موعد جديد
     */
    public function bookAppointment(Request $request)
    {
        try {
            $user = $request->user();

            // التحقق من أن المستخدم مريض
            if ($user->role !== 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only patients can book appointments'
                ], 403);
            }

            // التحقق من صحة البيانات
            $validator = Validator::make($request->all(), [
                'doctor_id' => 'required|exists:users,id',
                'appointment_date' => 'required|date|after_or_equal:today',
                'appointment_time' => 'required|date_format:H:i',
            ], [
                'doctor_id.required' => 'يجب اختيار الطبيب',
                'doctor_id.exists' => 'الطبيب غير موجود',
                'appointment_date.required' => 'يجب اختيار التاريخ',
                'appointment_date.after_or_equal' => 'التاريخ يجب أن يكون اليوم أو مستقبلاً',
                'appointment_time.required' => 'يجب اختيار الوقت',
                'appointment_time.date_format' => 'صيغة الوقت غير صحيحة',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // التحقق من أن الطبيب موجود ودوره طبيب
            $doctor = User::where('id', $request->doctor_id)
                ->where('role', 'doctor')
                ->first();

            if (!$doctor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid doctor selected'
                ], 400);
            }

            // التحقق من عدم وجود موعد مكرر
            $existingAppointment = Appointment::where('doctor_id', $request->doctor_id)
                ->where('appointment_date', $request->appointment_date)
                ->where('appointment_time', $request->appointment_time)
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا الوقت محجوز بالفعل. يرجى اختيار وقت آخر'
                ], 409);
            }

            // التحقق من أن المريض ليس لديه موعد في نفس الوقت مع طبيب آخر
            $patientConflict = Appointment::where('patient_id', $user->id)
                ->where('appointment_date', $request->appointment_date)
                ->where('appointment_time', $request->appointment_time)
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($patientConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'لديك موعد آخر في هذا الوقت'
                ], 409);
            }

            // إنشاء الموعد
            $appointment = Appointment::create([
                'patient_id' => $user->id,
                'doctor_id' => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'status' => 'pending',
                'doctor_notes' => null,
            ]);

            // تحميل العلاقات
            $appointment->load(['patient', 'doctor']);

            return response()->json([
                'success' => true,
                'message' => 'تم حجز الموعد بنجاح',
                'data' => $appointment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حجز الموعد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب مواعيد المريض
     */
    public function getPatientAppointments(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // جلب جميع مواعيد المريض مع تفاصيل الطبيب
            $appointments = Appointment::where('patient_id', $user->id)
                ->with(['patient', 'doctor', 'doctor.doctor.specialty'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            // تقسيم المواعيد إلى سابقة وقادمة
            $now = now()->toDateString();
            
            $upcoming = $appointments->filter(function ($appointment) use ($now) {
                return $appointment->appointment_date >= $now 
                    && !in_array($appointment->status, ['cancelled', 'completed']);
            })->values();

            $past = $appointments->filter(function ($appointment) use ($now) {
                return $appointment->appointment_date < $now 
                    || in_array($appointment->status, ['cancelled', 'completed']);
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'all' => $appointments,
                    'upcoming' => $upcoming,
                    'past' => $past,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المواعيد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إلغاء موعد
     */
    public function cancelAppointment(Request $request, $appointmentId)
    {
        try {
            $user = $request->user();
            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموعد غير موجود'
                ], 404);
            }

            // التحقق من أن المستخدم هو صاحب الموعد
            if ($user->id !== $appointment->patient_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء هذا الموعد'
                ], 403);
            }

            // التحقق من أن الموعد لم يكتمل
            if ($appointment->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إلغاء موعد مكتمل'
                ], 400);
            }

            // التحقق من أن الموعد لم يتم إلغاؤه بالفعل
            if ($appointment->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'تم إلغاء هذا الموعد مسبقاً'
                ], 400);
            }

            $appointment->status = 'cancelled';
            $appointment->save();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الموعد بنجاح',
                'data' => $appointment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الموعد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب مواعيد الطبيب
     */
    public function getDoctorAppointments(Request $request, $doctorId)
    {
        try {
            $user = $request->user();

            // التحقق من الصلاحية
            if ($user->role === 'doctor' && $user->id != $doctorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $date = $request->query('date');
            $status = $request->query('status');

            $query = Appointment::where('doctor_id', $doctorId)
                ->with(['patient', 'doctor']);

            if ($date) {
                $query->whereDate('appointment_date', $date);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get();

            // إحصائيات المواعيد
            $stats = [
                'total' => $appointments->count(),
                'pending' => $appointments->where('status', 'pending')->count(),
                'confirmed' => $appointments->where('status', 'confirmed')->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'cancelled' => $appointments->where('status', 'cancelled')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'appointments' => $appointments,
                    'statistics' => $stats,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المواعيد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث حالة الموعد (للطبيب فقط)
     */
    public function updateAppointmentStatus(Request $request, $appointmentId)
    {
        try {
            $user = $request->user();
            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموعد غير موجود'
                ], 404);
            }

            // التحقق من أن المستخدم هو الطبيب المعني
            if ($user->id !== $appointment->doctor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتحديث هذا الموعد'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,confirmed,cancelled,completed',
            ], [
                'status.required' => 'يجب تحديد الحالة',
                'status.in' => 'الحالة غير صحيحة',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // التحقق من صحة التحويلات
            if ($appointment->status === 'completed' && $request->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تغيير حالة موعد مكتمل'
                ], 400);
            }

            if ($appointment->status === 'cancelled' && $request->status !== 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تغيير حالة موعد ملغي'
                ], 400);
            }

            $appointment->status = $request->status;
            $appointment->save();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الموعد بنجاح',
                'data' => $appointment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الحالة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إضافة ملاحظات طبية (للطبيب فقط)
     */
    public function addDoctorNotes(Request $request, $appointmentId)
    {
        try {
            $user = $request->user();
            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموعد غير موجود'
                ], 404);
            }

            // التحقق من أن المستخدم هو الطبيب المعني
            if ($user->id !== $appointment->doctor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإضافة ملاحظات لهذا الموعد'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'doctor_notes' => 'required|string|min:3|max:1000',
            ], [
                'doctor_notes.required' => 'يجب إدخال الملاحظات الطبية',
                'doctor_notes.min' => 'الملاحظات يجب أن تكون 3 أحرف على الأقل',
                'doctor_notes.max' => 'الملاحظات يجب أن لا تتجاوز 1000 حرف',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // تحديث الملاحظات وتغيير الحالة إلى مكتمل
            $appointment->doctor_notes = $request->doctor_notes;
            $appointment->status = 'completed';
            $appointment->save();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الملاحظات الطبية بنجاح',
                'data' => $appointment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الملاحظات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب تفاصيل موعد محدد
     */
    public function getAppointmentDetails($appointmentId)
    {
        try {
            $appointment = Appointment::with(['patient', 'doctor', 'doctor.doctor.specialty'])
                ->find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموعد غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $appointment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الموعد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب المواعيد المتاحة لطبيب في تاريخ معين
     */
    public function getAvailableTimeSlots(Request $request, $doctorId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->query('date');
            
            // جلب المواعيد المحجوزة في هذا التاريخ
            $bookedAppointments = Appointment::where('doctor_id', $doctorId)
                ->where('appointment_date', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->pluck('appointment_time')
                ->toArray();

            // الأوقات المتاحة (من 9 صباحاً إلى 5 مساءً)
            $allSlots = [];
            $startHour = 9;
            $endHour = 17;
            $interval = 30; // دقيقة

            for ($hour = $startHour; $hour < $endHour; $hour++) {
                for ($minute = 0; $minute < 60; $minute += $interval) {
                    $time = sprintf('%02d:%02d', $hour, $minute);
                    $allSlots[] = [
                        'time' => $time,
                        'available' => !in_array($time, $bookedAppointments),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'slots' => $allSlots,
                    'booked_slots' => $bookedAppointments,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الأوقات المتاحة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إحصائيات الطبيب
     */
    public function getDoctorStatistics($doctorId)
    {
        try {
            $user = request()->user();
            
            if ($user->role === 'doctor' && $user->id != $doctorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $today = now()->toDateString();
            $weekAgo = now()->subDays(7)->toDateString();

            $stats = [
                'today_appointments' => Appointment::where('doctor_id', $doctorId)
                    ->where('appointment_date', $today)
                    ->count(),
                
                'today_pending' => Appointment::where('doctor_id', $doctorId)
                    ->where('appointment_date', $today)
                    ->where('status', 'pending')
                    ->count(),
                
                'weekly_appointments' => Appointment::where('doctor_id', $doctorId)
                    ->whereBetween('appointment_date', [$weekAgo, $today])
                    ->count(),
                
                'total_patients' => Appointment::where('doctor_id', $doctorId)
                    ->where('status', 'completed')
                    ->distinct('patient_id')
                    ->count('patient_id'),
                
                'total_appointments' => Appointment::where('doctor_id', $doctorId)
                    ->count(),
                
                'completed_appointments' => Appointment::where('doctor_id', $doctorId)
                    ->where('status', 'completed')
                    ->count(),
                
                'cancelled_appointments' => Appointment::where('doctor_id', $doctorId)
                    ->where('status', 'cancelled')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}