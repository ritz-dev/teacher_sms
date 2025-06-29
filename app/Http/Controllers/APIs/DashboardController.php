<?php

namespace App\Http\Controllers\APIs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        try {

            $validation = $request->validate([
                'owner_slug' => 'required|string|max:255',
            ]);

            $today = Carbon::now()->format('l');

            $currentAcademicYear = DB::table('academic_years')
                // ->where('start_date', '<=', $todayDate)
                // ->where('end_date', '>=', $todayDate)
                ->where('status', 'In Progress')
                ->value('slug');

            $totalStudents = DB::table('weekly_schedules as ws')
                ->join('student_enrollments as se', 'ws.academic_class_section_slug', '=', 'se.academic_class_section_slug')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('acs.academic_year_slug', $currentAcademicYear)
                ->whereNull('se.deleted_at')
                ->distinct('se.student_slug')
                ->count('se.student_slug');
            
            $subjects = DB::table('weekly_schedules as ws')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('acs.academic_year_slug', $currentAcademicYear)
                ->select('ws.subject_slug', 'ws.subject_name')
                ->distinct()
                ->get();

            $weeklySchedule = DB::table('weekly_schedules as ws')
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

            $todaySchedule = $weeklySchedule->where('day_of_week', $today)->values();
            
            $query = DB::table('assessments')
                ->join('academic_class_sections as acs', 'assessments.academic_class_section_slug', '=', 'acs.slug')
                ->where('acs.academic_year_slug', $currentAcademicYear)
                ->where('assessments.teacher_slug', $validation['owner_slug']);
                

            $exam = (clone $query)->where('assessments.type', 'Exam')->where('assessments.due_date', '>=', Carbon::now()->format('Ymd') )->count();
            $assignment = (clone $query)->where('assessments.type', 'Assignment')->where('assessments.due_date', '>=', Carbon::now()->format('Ymd') )->count();
            $quiz = (clone $query)->where('assessments.type', 'Quiz')->where('assessments.due_date', '>=', Carbon::now()->format('Ymd') )->count();

            $attendance = DB::table('academic_attendances as aa')
                ->join('academic_class_sections as acs', 'aa.academic_class_section_slug', '=', 'acs.slug')
                ->where('acs.academic_year_slug', $currentAcademicYear);
                // ->where('aa.approved_slug', $validation['owner_slug']);
            
            $presentCount = (clone $attendance)->where('aa.status', 'Present')->count();
            $absentCount = (clone $attendance)->where('aa.status', 'Absent')->count();
            $lateCount = (clone $attendance)->where('aa.status', 'Late')->count();
            $excusedCount = (clone $attendance)->where('aa.status', 'Excused')->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard summary fetched successfully',
                'total_students' => $totalStudents,
                'subjects' => $subjects,
                'today_schedule' => $todaySchedule,
                'weekly_schedule' => $weeklySchedule,
                'assessments' => [
                    'exam' => $exam,
                    'assignment' => $assignment,
                    'quiz' => $quiz,
                ],
                'attendance' => [
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'excused' => $excusedCount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching dashboard summary',
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }

    }
}
