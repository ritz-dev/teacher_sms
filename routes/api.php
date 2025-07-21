<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIs\TeacherController;
use App\Http\Controllers\APIs\DashboardController;
use App\Http\Controllers\APIs\AssessmentController;
use App\Http\Controllers\APIs\AttendanceController;
use App\Http\Controllers\APIs\AssessmentResultController;

Route::post('dashboard', [DashboardController::class, 'summary']);
Route::post('students', [TeacherController::class, 'getStudent']);
Route::post('subjects', [TeacherController::class, 'getSubjects']);
Route::post('student-attendance', [TeacherController::class, 'getStudentAttendance']);
Route::post('attendance/store', [TeacherController::class, 'storeStudentAttendance']);
Route::post('academic-class-section', [TeacherController::class, 'getAcademicClassSection']);
Route::post('students/attendance', [TeacherController::class, 'getAttendanceByStudent']);
Route::post('students/attendance/chart/pie', [TeacherController::class, 'getAttendancePieChart']);
Route::post('students/attendance/chart/bar', [TeacherController::class, 'getAttendanceBarChart']);
Route::post('weekly-schedule', [TeacherController::class, 'getWeeklySchedule']);
Route::post('teacher-profile',[TeacherController::class,'getTeacherProfile']);

Route::prefix('attendance')->group(function(){
    Route::post('/',[AttendanceController::class,'index']);
    Route::post('store',[AttendanceController::class,'store']);
    Route::post('show',[AttendanceController::class,'show']);
    Route::post('update',[AttendanceController::class,'update']);
    Route::post('action',[AttendanceController::class,'handleAction']);
    Route::post('delete',[AttendanceController::class,'delete']);
});

Route::prefix('attendances')->group(function () {
    Route::post('get-by-student', [AttendanceController::class, 'getAttendanceByStudent']);
});

Route::prefix('assessments')->group(function () {
    Route::post('', [AssessmentController::class, 'index']);
    Route::post('store', [AssessmentController::class, 'store']);
    Route::post('update', [AssessmentController::class, 'update']);
    Route::post('delete', [AssessmentController::class, 'delete']);
    Route::post('get-by-student', [AssessmentController::class, 'getAssessmentsByStudent']);
});

Route::prefix('assessments-result')->group(function () {
    Route::post('', [AssessmentResultController::class, 'index']);
    Route::post('store', [AssessmentResultController::class, 'store']);
    Route::post('update', [AssessmentResultController::class, 'update']);
    Route::post('delete', [AssessmentResultController::class, 'delete']);
    Route::post('get-by-student', [AssessmentResultController::class, 'getResultsByStudent']);
});