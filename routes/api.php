<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Apis\TeacherTimetableController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('timetable')->group(function(){

    Route::post('today-timetable',[TeacherTimetableController::class,'todayTimetable']);

    Route::post('class-timetable',[TeacherTimetableController::class,'classInTimetable']);

    Route::post('student-timetable',[TeacherTimetableController::class,'studentInTimetable']);

    Route::post('subject-timetable',[TeacherTimetableController::class,'subjectInTimetable']);

    Route::post('student-attendance-timetable',[TeacherTimetableController::class,'studentAttendanceInTimetable']);

});

