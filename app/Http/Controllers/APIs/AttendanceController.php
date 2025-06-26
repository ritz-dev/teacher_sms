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
                'student_slug' => 'required|string|exists:students,slug',
            ]);

            $currentAcademicYear = DB::table('academic_years')
                // ->where('start_date', '<=', $todayDate)
                // ->where('end_date', '>=', $todayDate)
                ->where('status', 'In Progress')
                ->value('slug');

            $attendance = DB::table('academic_attendances')
                ->where('student_slug', $validated['student_slug'])
                ->where('academic_year_slug', $currentAcademicYear)
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
