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

            $enrollments = DB::table('weekly_schedules as ws')
                ->join('student_enrollments as se', 'ws.academic_class_section_slug', '=', 'se.academic_class_section_slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
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
                    'se.academic_info',
                    'ws.slug as weekly_schedule_slug',
                )
                ->distinct()
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No students enrolled for this teacher.',
                ]);
            }

            $studentSlugs = $enrollments->pluck('student_slug')->unique()->values()->all();

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

            $combined = $enrollments->map(function ($enrollment) use ($studentsMap) {
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
        ]);
        
        try {
            $subjects = DB::table('weekly_schedules as ws')
                ->join('subjects as sub', 'ws.subject_slug', '=', 'sub.slug')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_years as ay', 'acs.academic_year_slug', '=', 'ay.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->select(
                    'sub.slug as subject_slug',
                    'sub.name as subject_name',
                    'ay.slug as academic_year_slug',
                    'ay.year as academic_year_name',
                    'ac.slug as class_slug',
                    'ac.name as class_name',
                    'sec.slug as section_slug',
                    'sec.name as section_name'
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
                'data' => $records
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

}
