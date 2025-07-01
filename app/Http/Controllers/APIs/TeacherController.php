<?php

namespace App\Http\Controllers\APIs;
use Carbon\Carbon;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class TeacherController extends Controller
{
    public function getStudent (Request $request) 
    {
        try {
            $validation = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'academic_class_section_slug' => 'nullable|string|max:255',
            ]);

            $currentAcademicYear = DB::table('academic_years')
                // ->where('start_date', '<=', $todayDate)
                // ->where('end_date', '>=', $todayDate)
                ->where('status', 'In Progress')
                ->value('slug');

            $enrollments = DB::table('weekly_schedules as ws')
                ->join('student_enrollments as se', 'ws.academic_class_section_slug', '=', 'se.academic_class_section_slug')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('acs.academic_year_slug', $currentAcademicYear)
                ->whereNull('se.deleted_at')
                ->where('se.status', 'active')
                ->when($validation['academic_class_section_slug'] ?? null, function ($query, $sectionSlug) {
                    $query->where('se.academic_class_section_slug', $sectionSlug);
                })
                ->select(
                    'se.slug as enrollment_slug',
                    'se.student_slug',
                    'se.student_name',
                    'se.roll_number',
                    'se.enrollment_type',
                    'se.admission_date',
                    'se.status',
                    'se.academic_class_section_slug',
                    'ac.name as class_name',
                    'sec.name as section_name',
                    'se.academic_info'
                )
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No students enrolled for this teacher.',
                ]);
            }

            // Make enrollments unique by student_slug
            $uniqueEnrollments = $enrollments->unique('student_slug')->values();

            $studentSlugs = $uniqueEnrollments->pluck('student_slug')->all();

            $studentApiUrl = config('services.user.url') . 'students';

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                // 'Authorization' => $request->header('Authorization'),
            ])->post($studentApiUrl, ['slugs' => $studentSlugs]);

            if (!$response->ok()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch student data from external service.',
                ], 500);
            }

            $studentsData = $response->json('data') ?? [];
            $studentsMap = collect($studentsData)->keyBy('slug');

            $combined = $uniqueEnrollments->map(function ($enrollment) use ($studentsMap) {
                $enrollment = (array) $enrollment;
                $enrollment['student'] = $studentsMap->get($enrollment['student_slug']) ?? null;
                return $enrollment;
            });

            return response()->json([
                'status' => 'success',
                'data' => $combined,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSubjects(Request $request) 
    {
        $validation = $request->validate([
            'owner_slug' => 'required|string|max:255',
            'academic_class_section_slug' => 'nullable|string|max:255',
        ]);

        $currentAcademicYear = DB::table('academic_years')
                // ->where('start_date', '<=', $todayDate)
                // ->where('end_date', '>=', $todayDate)
                ->where('status', 'In Progress')
                ->value('slug');
        
        try {
            $subjects = DB::table('weekly_schedules as ws')
                ->join('subjects as sub', 'ws.subject_slug', '=', 'sub.slug')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_years as ay', 'acs.academic_year_slug', '=', 'ay.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('acs.academic_year_slug', $currentAcademicYear)
                ->when($validation['academic_class_section_slug'] ?? null, function ($query, $sectionSlug) {
                    $query->where('ws.academic_class_section_slug', $sectionSlug);
                })
                ->select(
                    'sub.slug as subject_slug',
                    'sub.name as subject_name',
                    'ay.slug as academic_year_slug',
                    'ay.year as academic_year_name',
                    'ac.slug as class_slug',
                    'ac.name as class_name',
                    'sec.slug as section_slug',
                    'sec.name as section_name',
                    'acs.slug as academic_class_section_slug',
                )
                ->distinct()
                ->get();

            if ($subjects->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No subjects found for this teacher.',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $subjects,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStudentAttendance(Request $request) 
    {
        $validation = $request->validate([
            'owner_slug' => 'required|string|max:255',
            'weekly_schedule_slug' => 'required|string|max:255',
        ]);

        try {
            // Step 1: Get active enrollments for the section
            $enrollments = DB::table('weekly_schedules as ws')
                ->join('student_enrollments as se', 'ws.academic_class_section_slug', '=', 'se.academic_class_section_slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('ws.slug', $validation['weekly_schedule_slug'])
                ->where('se.status', 'active')
                ->whereNull('se.deleted_at')
                ->select(
                    'se.slug as enrollment_slug',
                    'se.student_slug',
                    'se.student_name',
                    'se.roll_number',
                    'se.enrollment_type',
                    'se.admission_date',
                    'se.status',
                    'se.academic_class_section_slug',
                    'se.academic_info'
                )
                ->orderBy('se.roll_number')
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No students enrolled for this section.',
                ]);
            }

            // Step 2: Get full student data
            $studentSlugs = $enrollments->pluck('student_slug')->unique()->values()->all();

            $studentApiUrl = config('services.user.url') . 'students';
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($studentApiUrl, ['slugs' => $studentSlugs]);

            if (!$response->ok()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch student data from external service.',
                ], 500);
            }

            $studentsData = $response->json('data') ?? [];
            $studentsMap = collect($studentsData)->keyBy('slug');

            // Step 3: Get all attendance records for this weekly schedule
            $attendanceRecords = DB::table('academic_attendances')
                ->where('weekly_schedule_slug', $validation['weekly_schedule_slug'])
                ->whereIn('attendee_slug', $studentSlugs)
                ->where('attendee_type', 'student')
                ->select(
                    'attendee_slug',
                    'status',
                    'date',
                    'attendance_type',
                    'remark',
                    'id'
                )
                ->get()
                ->groupBy('attendee_slug');

            // Step 4: Combine data
            $combined = $enrollments->map(function ($enrollment) use ($studentsMap, $attendanceRecords) {
                $slug = $enrollment->student_slug;
                $attendance = optional($attendanceRecords->get($slug))->first();
            
                return [
                    'enrollment_slug' => $enrollment->enrollment_slug,
                    'student_slug' => $slug,
                    'student_name' => $enrollment->student_name,
                    'roll_number' => $enrollment->roll_number,
                    'enrollment_type' => $enrollment->enrollment_type,
                    'admission_date' => $enrollment->admission_date,
                    'academic_class_section_slug' => $enrollment->academic_class_section_slug,
                    'academic_info' => $enrollment->academic_info,
                    'student' => $studentsMap->get($slug),
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'status' => $attendance->status,
                        'date' => $attendance->date,
                        'type' => $attendance->attendance_type,
                        'remark' => $attendance->remark,
                    ] : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $combined,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeStudentAttendance(Request $request)
    {
        try {
            $validated = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'weekly_schedule_slug' => 'required|string|exists:weekly_schedules,slug',
                'academic_class_section_slug' => 'required|string|exists:academic_class_sections,slug',
                'attendance_type' => 'nullable|in:class,exam,event',
                'date' => 'required|date_format:Y-m-d',
                'attendances' => 'required|array|min:1',
                'attendances.*.attendee_type' => 'required|in:student,teacher',
                'attendances.*.attendee_slug' => 'required|string',
                'attendances.*.attendee_name' => 'nullable|string',
                'attendances.*.subject' => 'required|string',
                'attendances.*.academic_info' => 'nullable|string',
                'attendances.*.status' => 'required|in:present,absent,late,excused',
                'attendances.*.remark' => 'nullable|string',
            ]);

            $formattedDate = (int) Carbon::parse($validated['date'])->format('Ymd');
            $attendanceType = $validated['attendance_type'] ?? 'class';
            $timestamp = now();
            $approved = $validated['owner_slug'];

            $records = [];

            foreach ($validated['attendances'] as $item) {
                $previousHash =null;
                $calculatedHash = null;

                $records[] = [
                    'slug' => Str::uuid()->toString(),
                    'weekly_schedule_slug' => $validated['weekly_schedule_slug'],
                    'subject' => $item['subject'],
                    'academic_class_section_slug' => $validated['academic_class_section_slug'],
                    'academic_info' => $item['academic_info'] ?? null,

                    'attendee_slug' => $item['attendee_slug'],
                    'attendee_name' => $item['attendee_name'] ?? null,
                    'attendee_type' => $item['attendee_type'],
                    'status' => $item['status'],
                    'attendance_type' => $attendanceType,

                    'date' => $formattedDate,
                    'modified' => null,
                    'modified_by' => null,
                    'remark' => $item['remark'] ?? null,

                    'approved_slug' => $approved,

                    'previous_hash' => $previousHash,
                    'hash' => $calculatedHash,

                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            DB::beginTransaction();
            DB::table('academic_attendances')->insert($records);
            DB::commit();

            return response()->json([
                'message' => 'Attendances recorded successfully.',
                'data' => $approved
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to record attendance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAcademicClassSection (Request $request) {

        $validation = $request->validate([
            'owner_slug' => 'required|string|max:255',
        ]);
        
        try {
            $academicClassSections = DB::table('weekly_schedules as ws')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_years as ay', 'acs.academic_year_slug', '=', 'ay.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->select(
                    'acs.slug as academic_class_section_slug',
                    'ac.slug as class_slug',
                    'ac.name as class_name',
                    'sec.slug as section_slug',
                    'sec.name as section_name',
                    'acs.academic_year_slug',
                    'ay.year as academic_year_name'
                )
                ->distinct()
                ->get();

            if ($academicClassSections->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No academic class sections found for this teacher.',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $academicClassSections,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAttendanceByStudent (Request $request) {
        try {
            $validation = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'student_slug' => 'required|string|max:255',
                'academic_class_section_slug' => 'nullable|string|max:255',
            ]);

            $attendanceRecords = DB::table('academic_attendances')
                ->where('attendee_slug', $validation['student_slug'])
                ->where('attendee_type', 'student')
                ->when($validation['academic_class_section_slug'] ?? null, function ($query, $sectionSlug) {
                    $query->where('academic_class_section_slug', $sectionSlug);
                })
                ->select(
                    'slug',
                    'weekly_schedule_slug',
                    'subject',
                    'academic_class_section_slug',
                    'academic_info',
                    'attendee_slug',
                    'attendee_name',
                    'status',
                    'attendance_type',
                    'date',
                    'remark'
                )
                ->orderBy('date', 'desc')
                ->get();

            if ($attendanceRecords->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No attendance records found for this student.',
                ]);
            }

            $attendanceRecords = $attendanceRecords->map(function ($record) {
                $record->date = Carbon::createFromFormat('Ymd', (string)$record->date)->format('Y-m-d');
                return $record;
            });

            return response()->json([
                'status' => 'success',
                'data' => $attendanceRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAttendancePieChart (Request $request) {
        try {
            $validation = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'student_slug' => 'required|string|max:255',
                'academic_class_section_slug' => 'nullable|string|max:255',
            ]);

            $summary = DB::table('academic_attendances')
                ->selectRaw("
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused,
                    COUNT(*) as total
                ")
                ->where('attendee_slug', $validation['student_slug'])
                ->where('attendee_type', 'student')
                ->when($validation['academic_class_section_slug'] ?? null, function ($query, $sectionSlug) {
                    $query->where('academic_class_section_slug', $sectionSlug);
                })
                ->first();

            return response()->json([
                'present' => (int) $summary->present,
                'absent' => (int) $summary->absent,
                'late' => (int) $summary->late,
                'excused' => (int) $summary->excused,
                'total' => (int) $summary->total,
            ]);
        }
            catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
        }
    }

    public function getAttendanceBarChart(Request $request)
    {
        try {
            $validated = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'student_slug' => 'required|string|max:255',
                'academic_class_section_slug' => 'nullable|string|max:255',
                'date' => 'required|integer', // format: Ymd e.g. 20250522
                'days' => 'required|in:week,month',
            ]);
    
            // Parse the date (e.g., 20250522)
            $baseDate = Carbon::createFromFormat('Ymd', $validated['date']);
    
            // Calculate from and to dates based on 'week' or 'month'
            if ($validated['days'] === 'week') {
                $fromDate = $baseDate->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
                $toDate = $baseDate->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay();
            } elseif ($validated['days'] === 'month') {
                $fromDate = $baseDate->copy()->startOfMonth()->startOfDay();
                $toDate = $baseDate->copy()->endOfMonth()->endOfDay();
            }
    
            $fromInt = (int) $fromDate->format('Ymd');
            $toInt = (int) $toDate->format('Ymd');
    
            // Query attendance data
            $rawData = DB::table('academic_attendances')
                ->selectRaw("
                    date as day,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
                ")
                ->where('attendee_slug', $validated['student_slug'])
                ->where('attendee_type', 'student')
                ->whereBetween('date', [$fromInt, $toInt])
                ->when($validated['academic_class_section_slug'] ?? null, function ($query, $sectionSlug) {
                    $query->where('academic_class_section_slug', $sectionSlug);
                })
                ->groupBy('day')
                ->orderBy('day', 'asc')
                ->get();
    
            // Build full range
            $totalDays = $fromDate->diffInDays($toDate) + 1;
            $fullDays = collect();
    
            for ($i = 0; $i < $totalDays; $i++) {
                $dayCarbon = $fromDate->copy()->addDays($i);
                $dayInt = (int) $dayCarbon->format('Ymd');
                $entry = $rawData->firstWhere('day', $dayInt);
    
                $fullDays->push([
                    'date' => $dayCarbon->format('Y-m-d'),
                    'day' => $dayCarbon->format('D'), // Mon, Tue, etc.
                    'present' => isset($entry) ? (int)$entry->present : 0,
                    'absent' => isset($entry) ? (int)$entry->absent : 0,
                    'late' => isset($entry) ? (int)$entry->late : 0,
                    'excused' => isset($entry) ? (int)$entry->excused : 0,
                ]);
            }
    
            return response()->json($fullDays);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getWeeklySchedule(Request $request) 
    {
        $validation = $request->validate([
            'owner_slug' => 'required|string|max:255',
        ]);

        try {
            $currentAcademicYear = DB::table('academic_years')
                ->where('status', 'In Progress')
                ->value('slug');

            $weeklySchedules = DB::table('weekly_schedules as ws')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('acs.academic_year_slug', $currentAcademicYear) // 👈 correct filtering here
                ->select(
                    'ws.slug as weekly_schedule_slug',
                    'ws.academic_class_section_slug',
                    'ws.day_of_week',
                    'ac.name as class_name',
                    'sec.name as section_name',
                    'ws.subject_name',
                    'ws.start_time',
                    'ws.end_time'
                )
                ->orderByRaw("FIELD(ws.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
                ->orderBy('ws.start_time')
                ->get();


            if ($weeklySchedules->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No weekly schedules found for this teacher.',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $weeklySchedules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
