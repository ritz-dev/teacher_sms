<?php

namespace App\Http\Controllers\APIs;

use Exception;
use Carbon\Carbon;
use App\Models\Subject;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use App\Models\DailySchedule;
use App\Models\StudentEnrollment;
use App\Models\AcademicAttendance;
use App\Http\Controllers\Controller;
use App\Models\AcademicClassSection;
use Illuminate\Support\Facades\Http;

 // $apiGatewayUrl = config('services.api_gateway.url'). 'me';
// $api_response = Http::withHeaders([
//     'Accept' => 'application/json',
//     'Authorization' => request()->header('Authorization'),
// ])->post($apiGatewayUrl, []);

class TeacherTimetableController extends Controller
{
    public function todayTimetable(Request $request){
        
        try{
            $request->validate([
                'teacher_id' => 'required',
            ]);

            if($request->date){
                $date = $request->date;

                $teacher_id = $request->teacher_id;

                $todayTimetables = DailySchedule::with(['academicClassSection.class','academicClassSection.section','subject','academicAttendances'])
                                                ->whereDate('date',$date)
                                                ->where('teacher_id',$teacher_id)
                                                ->get();

            }else{
                $today = Carbon::today()->toDateString();

                logger($today);

                $teacher_id = $request->teacher_id;

                $todayTimetables = DailySchedule::with(['academicClassSection.class','academicClassSection.section','subject','academicAttendances'])
                                                ->whereDate('date',$today)
                                                ->where('teacher_id',$teacher_id)
                                                ->get();
            }
            
            return response()->json([
                'success' => true,
                'data' => $todayTimetables
            ]);

            // return response()->json(
            //     $todayTimetables
            // );

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }

    public function classInTimetable(Request $request){
        try{
            $request->validate([
                'teacher_id' => 'required',
            ]);

            $teacher_id = $request->teacher_id;
            $academic_year_id = $request->academic_year_id;

            $sectionIds = array_values(
                DailySchedule::where('teacher_id', $teacher_id)
                    ->pluck('academic_class_section_id')
                    ->unique()
                    ->toArray()
            );
            // logger($sectionIds);
            
            $classes = AcademicClassSection::with('class')
                                        ->where('academic_year_id', $academic_year_id)
                                        ->whereIn('id', $sectionIds)
                                        ->get()
                                        ->map(function ($section) {
                                            return [
                                                'class_name' => $section->class->name ?? null,
                                                'section_name' => $section->section->name ?? null,
                                            ];
                                        })
                                        ->unique('class_name','section_name')
                                        ->values();

            return response()->json([
                'success' => true,
                // 'year' => $sections,
                'data' => $classes
        ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }

    public function studentInTimetable(Request $request){
        try{
            $request->validate([
                'teacher_id' => 'required',
            ]);

            $teacher_id = $request->teacher_id;
            $academic_year_id = $request->academic_year_id;

            $sectionIds = DailySchedule::where('teacher_id', $teacher_id)
                                        ->whereHas('academicClassSection', function ($query) use ($academic_year_id) {
                                            $query->where('academic_year_id', $academic_year_id);
                                        })
                                        ->pluck('academic_class_section_id')
                                        ->unique()
                                        ->toArray();
        
            $studentIds = StudentEnrollment::with('academicClassSection')
                                        ->whereIn('academic_class_section_id', $sectionIds)
                                        ->get()
                                        ->map(function ($section) {
                                            return [
                                                'student_id' => $section->student_id ?? null,
                                            ];
                                        })
                                        ->pluck('student_id')
                                        ->unique()
                                        ->values()
                                        ->toArray();

            $studentsApiUrl = config('services.user_gateway.url') . 'students';

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $request->header('Authorization'),
            ])->post($studentsApiUrl, []);

            if (!$response->ok()) {
                $this->command->error('Failed to fetch students from user management service.');
                return;
            }

            $students = $response->json() ?? [];

            $enrollments = collect($students)->whereIn('slug', $studentIds)->values()->all();

            return response()->json([
                'success' => true,
                'data' => $enrollments
        ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }

    public function subjectInTimetable(Request $request){

        try{
            $request->validate([
                'teacher_id' => 'required',
            ]);

            $teacher_id = $request->teacher_id;
            $academic_year_id = $request->academic_year_id;

            $subjectIds = DailySchedule::where('teacher_id', $teacher_id)
                                        ->whereHas('academicClassSection', function ($query) use ($academic_year_id) {
                                            $query->where('academic_year_id', $academic_year_id);
                                        })
                                        ->pluck('subject_id')
                                        ->unique()
                                        ->toArray();
        
            $subjects = Subject::whereIn('id', $subjectIds)
                                ->get()
                                ->map(function ($subject) {
                                    return [
                                        'name' => $subject->name ?? null,
                                    ];
                                })
                                ->unique('name')
                                ->values();
        
            return response()->json([
                'success' => true,
                'data' => $subjects
        ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }

    public function studentAttendanceInTimetable(Request $request){

        try{
            $request->validate([
                'teacher_id' => 'required',
            ]);

            $teacher_id = $request->teacher_id;
            $academic_year_id = $request->academic_year_id;

            $scheduleIds = DailySchedule::where('teacher_id', $teacher_id)
                                        ->whereHas('academicClassSection', function ($query) use ($academic_year_id) {
                                            $query->where('academic_year_id', $academic_year_id);
                                        })
                                        ->pluck('id')
                                        ->unique()
                                        ->toArray();
        
            $attendances = AcademicAttendance::whereIn('schedule_id', $scheduleIds)
                                ->where('status','present')
                                ->get();
                        
        
            return response()->json([
                'success' => true,
                'data' => $attendances
        ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }
}
