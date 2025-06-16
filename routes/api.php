<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIs\TeacherController;
use App\Http\Controllers\APIs\DashboardController;
use App\Http\Controllers\APIs\TeacherTimetableController;

// Route::prefix('timetable')->group(function(){

//     Route::post('today-timetable',[TeacherTimetableController::class,'todayTimetable']);

//     Route::post('class-timetable',[TeacherTimetableController::class,'classInTimetable']);

//     Route::post('student-timetable',[TeacherTimetableController::class,'studentInTimetable']);

//     Route::post('subject-timetable',[TeacherTimetableController::class,'subjectInTimetable']);

//     Route::post('student-attendance-timetable',[TeacherTimetableController::class,'studentAttendanceInTimetable']);

// });

Route::post('dashboard', [DashboardController::class, 'summary']);
Route::post('students', [TeacherController::class, 'getStudent']);
Route::post('subjects', [TeacherController::class, 'getSubjects']);
Route::post('student-attendance', [TeacherController::class, 'getStudentAttendance']);
Route::post('student-attendance/store', [TeacherController::class, 'storeStudentAttendance']);
Route::post('academic-class-section', [TeacherController::class, 'getAcademicClassSection']);

