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

            $totalStudents = DB::table('weekly_schedules as ws')    
                ->join('student_enrollments as se', 'ws.academic_class_section_slug', '=', 'se.academic_class_section_slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->whereNull('se.deleted_at')
                ->distinct('se.student_slug')
                ->count('se.student_slug');
            
            $subjects = DB::table('weekly_schedules')
                ->where('teacher_slug', $validation['owner_slug'])
                ->distinct()
                ->pluck('subject_name')
                ->count('se.subject_name');

            $todaySchedule = DB::table('weekly_schedules as ws')
                ->join('academic_class_sections as acs', 'ws.academic_class_section_slug', '=', 'acs.slug')
                ->join('academic_classes as ac', 'acs.class_slug', '=', 'ac.slug')
                ->join('sections as sec', 'acs.section_slug', '=', 'sec.slug')
                ->where('ws.teacher_slug', $validation['owner_slug'])
                ->where('ws.day_of_week', $today)
                ->select(
                    'ac.name as class_name',
                    'sec.name as section_name',
                    'ws.day_of_week',
                    'ws.subject_name',
                    'ws.start_time',
                    'ws.end_time'
                )
                ->orderBy('ws.start_time')
                ->get();
            
            return response()->json([
                // 'teacher_slug' => $teacher_slug,
                'total_students' => $totalStudents,
                'subjects' => $subjects,
                'today_schedule' => $todaySchedule,
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
