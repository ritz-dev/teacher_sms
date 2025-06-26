<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function getAttendanceByStudent(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_slug' => 'required|string',
            ]);

            $currentAcademicYear = DB::table('academic_years')
                // ->where('start_date', '<=', $todayDate)
                // ->where('end_date', '>=', $todayDate)
                ->where('status', 'In Progress')
                ->value('slug');

            $attendances = DB::table('academic_attendances')
                ->join('academic_class_sections', 'academic_attendances.academic_class_section_slug', '=', 'academic_class_sections.slug')
                ->where('academic_attendances.attendee_type', 'student')
                ->where('academic_class_sections.academic_year_slug', $currentAcademicYear)
                ->select(
                    'academic_attendances.slug as attendance_slug',
                    'academic_attendances.date as date',
                    'academic_attendances.status as status',
                    'academic_attendances.remark as remark',
                    'academic_class_sections.slug as academic_class_section_slug',
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attendance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
