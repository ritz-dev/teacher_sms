<?php

namespace App\Http\Controllers\APIs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AcademicAttendance;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'weekly_schedule_slug' => ['nullable', 'string'],
                'academic_class_section_slug' => ['nullable', 'string', 'exists:academic_class_sections,slug'],
                'attendee_slug' => ['nullable', 'string'],
                'attendee_type' => ['nullable', 'string'],
                'status' => ['nullable', 'in:present,absent,late,excused'],
                'attendance_type' => ['nullable', 'in:class,exam,event'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'limit' => ['nullable', 'integer', 'min:1'],
                'skip' => ['nullable', 'integer', 'min:0'],
            ]);

            $query = DB::table('academic_attendances')
                ->leftJoin('weekly_schedules', 'academic_attendances.weekly_schedule_slug', '=', 'weekly_schedules.slug')
                ->select('academic_attendances.*', 'weekly_schedules.start_time', 'weekly_schedules.end_time');

            if (!empty($validated['weekly_schedule_slug'])) {
                $query->where('academic_attendances.weekly_schedule_slug', $validated['weekly_schedule_slug']);
            }
            if (!empty($validated['academic_class_section_slug'])) {
                $query->where('academic_attendances.academic_class_section_slug', $validated['academic_class_section_slug']);
            }
            if (!empty($validated['attendee_slug'])) {
                $query->where('academic_attendances.attendee_slug', $validated['attendee_slug']);
            }
            if (!empty($validated['attendee_type'])) {
                $query->where('academic_attendances.attendee_type', $validated['attendee_type']);
            }
            if (!empty($validated['status'])) {
                $query->where('academic_attendances.status', $validated['status']);
            }
            if (!empty($validated['attendance_type'])) {
                $query->where('academic_attendances.attendance_type', $validated['attendance_type']);
            }
            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                $startInt = (int) \Carbon\Carbon::parse($validated['start_date'])->format('Ymd');
                $endInt = (int) \Carbon\Carbon::parse($validated['end_date'])->format('Ymd');
                $query->whereBetween('academic_attendances.date', [$startInt, $endInt]);
            } elseif (!empty($validated['start_date'])) {
                $startInt = (int) \Carbon\Carbon::parse($validated['start_date'])->format('Ymd');
                $query->where('academic_attendances.date', '>=', $startInt);
            } elseif (!empty($validated['end_date'])) {
                $endInt = (int) \Carbon\Carbon::parse($validated['end_date'])->format('Ymd');
                $query->where('academic_attendances.date', '<=', $endInt);
            }
            if (!empty($validated['start_time'])) {
                $query->where('weekly_schedules.start_time', '>=', $validated['start_time']);
            }
            if (!empty($validated['end_time'])) {
                $query->where('weekly_schedules.end_time', '<=', $validated['end_time']);
            }

            $query->orderByDesc('academic_attendances.date');

            $total = (clone $query)->count();

            if (!empty($validated['skip'])) {
                $query->skip($validated['skip']);
            }
            if (!empty($validated['limit'])) {
                $query->take($validated['limit']);
            }

            $results = collect($query->get());

            $grouped = $results->groupBy('attendee_type')->map(function ($items) {
                return collect($items)->pluck('attendee_slug')->unique()->values()->all();
            });

            $attendeeData = [];

            foreach ($grouped as $type => $slugs) {
                $baseUrl = config('services.user.url');
                $endpoint = match ($type) {
                    'student' => "$baseUrl" . "students",
                    'teacher' => "$baseUrl" . "teachers",
                    default => null,
                };

                if (!$endpoint) continue;

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Accept' => 'application/json',
                ])->post($endpoint, ['slugs' => $slugs]);

                if ($response->successful()) {
                    $attendeeData[$type] = collect($response->json('data'))->keyBy('slug')->toArray();
                }
            }

            $results = $results->map(function ($item) use ($attendeeData) {
                $attendee = $attendeeData[$item->attendee_type][$item->attendee_slug] ?? null;
                $data = (array) $item;
                $data['date'] = \Carbon\Carbon::createFromFormat('Ymd', $item->date)->toDateString();
                $data['attendee'] = $attendee;
                return $data;
            });

            return response()->json([
                'status' => 'OK! The request was successful',
                'total' => $total,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'owner_slug' => 'required|string',
                'attendances' => 'required|array|min:1',
                'attendances.*.attendee_type' => 'required|in:student,teacher',
                'attendances.*.attendee_slug' => 'required|string',
                'attendances.*.weekly_schedule_slug' => 'required|exists:weekly_schedules,slug',
                'attendances.*.attendee_name' => 'nullable|string',
                'attendances.*.subject' => 'required|string',
                'attendances.*.academic_class_section_slug' => 'required|string',
                'attendances.*.academic_info' => 'nullable|string',
                'attendances.*.status' => 'required|in:present,absent,late,excused',
                'attendances.*.remark' => 'nullable|string',
                'attendances.*.attendance_type' => 'nullable|in:class,exam,event',
                'attendances.*.date' => 'required|date',
            ]);

            $inserted = [];

            DB::beginTransaction();

            foreach ($validated['attendances'] as $item) {
                // $previousHash = $this->blockchainService->getPreviousHash(AcademicAttendance::class);
                // $timestamp = now();
                // $calculatedHash = $this->blockchainService->calculateHash(
                //     $previousHash,
                //     json_encode($item),
                //     $timestamp->format('Y-m-d H:i:s')
                // );

                $dateInput = $item['date']; 
                $formattedDate = (int) Carbon::parse($dateInput)->format('Ymd');

                $data = [
                    'slug' => (string) Str::uuid(),
                    'weekly_schedule_slug' => $item['weekly_schedule_slug'],
                    'subject' => $item['subject'],
                    'academic_class_section_slug' => $item['academic_class_section_slug'],
                    'academic_info' => $item['academic_info'] ?? null,
                    'attendee_slug' => $item['attendee_slug'],
                    'attendee_name' => $item['attendee_name'],
                    'attendee_type' => $item['attendee_type'],
                    'status' => $item['status'],
                    'attendance_type' => $item['attendance_type'],
                    'approved_slug' => $validated['owner_slug'] ?? null,
                    'date' => $formattedDate,
                    'modified' => null,
                    'modified_by' => null,
                    'remark' => $item['remark'] ?? null,
                    'previous_hash' => null,
                    'hash' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $id = DB::table('academic_attendances')->insertGetId($data);
                $inserted[] = DB::table('academic_attendances')->where('id', $id)->first();
            }
            DB::commit();

            return response()->json([
                'message' => 'Attendances recorded successfully.',
                'data' => $inserted
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to record attendance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|exists:academic_attendances,slug',
            ]);

            $attendance = DB::table('academic_attendances')
                ->leftJoin('weekly_schedules', 'academic_attendances.weekly_schedule_slug', '=', 'weekly_schedules.slug')
                ->leftJoin('academic_class_sections', 'academic_attendances.academic_class_section_slug', '=', 'academic_class_sections.slug')
                ->where('academic_attendances.slug', $validated['slug'])
                ->select('academic_attendances.*', 'weekly_schedules.start_time', 'weekly_schedules.end_time', 'academic_class_sections.name as class_section_name')
                ->first();

            if (!$attendance) {
                return response()->json([
                    'message' => 'Attendance not found.',
                ], 404);
            }

            $baseUrl = config('services.user.url');
            $attendeeData = null;
    
            // Only proceed if attendee_type and attendee_slug are set
            if ($attendance->attendee_type && $attendance->attendee_slug) {
                $endpoint = match ($attendance->attendee_type) {
                    'student' => "{$baseUrl}students/show",
                    'teacher' => "{$baseUrl}teachers/show",
                    default => null,
                };
                
                if ($endpoint) {
                    $response = Http::withHeaders([
                        'Accept' => 'application/json',
                        // Optional: include auth
                        // 'Authorization' => $request->header('Authorization'),
                    ])->post($endpoint, [
                        'slug' => $attendance->attendee_slug,
                    ]);

                    if ($response->successful()) {
                        $attendeeData = $response->json('data');
                    }
                }
            }

            $attendance = (array) $attendance;
            $attendance['date'] = \Carbon\Carbon::createFromFormat('Ymd', $attendance['date'])->toDateString();
            $attendance['attendee'] = $attendeeData;

            return response()->json([
                'message' => 'Attendance retrieved successfully.',
                'data' => $attendance
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve attendance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'owner_slug' => 'required|string',
                'slug' => 'required|string|exists:academic_attendances,slug',
                'attendee_type' => 'required|in:student,teacher',
                'attendee_slug' => 'required|string',
                'weekly_schedule_slug' => 'required|exists:weekly_schedules,slug',
                'attendee_name' => 'nullable|string',
                'subject' => 'required|string',
                'academic_class_section_slug' => 'required|string',
                'academic_info' => 'nullable|string',
                'status' => 'required|in:present,absent,late,excused',
                'remark' => 'nullable|string',
                'attendance_type' => 'nullable|in:class,exam,event',
                'date' => 'required|date',
            ]);

            $attendance = DB::table('academic_attendances')->where('slug', $validated['slug'])->first();

            if (!$attendance) {
                return response()->json([
                    'message' => 'Attendance not found.',
                ], 404);
            }

            DB::beginTransaction();

            $dateInput = $request->input('date');
            $formattedDate = (int) \Carbon\Carbon::parse($dateInput)->format('Ymd');
            $timestamp = now();

            $updateData = [
                'weekly_schedule_slug' => $validated['weekly_schedule_slug'],
                'subject' => $validated['subject'],
                'academic_class_section_slug' => $validated['academic_class_section_slug'],
                'academic_info' => $validated['academic_info'] ?? null,
                'attendee_slug' => $validated['attendee_slug'],
                'attendee_name' => $validated['attendee_name'],
                'attendee_type' => $validated['attendee_type'],
                'status' => $validated['status'],
                'attendance_type' => $validated['attendance_type'] ?? 'class',
                'approved_slug' => $validated['owner_slug'] ?? null,
                'date' => $formattedDate,
                'modified' => $timestamp,
                'modified_by' => auth()->user()?->name ?? 'system',
                'remark' => $validated['remark'] ?? null,
                'previous_hash' => null,
                'hash' => null,
                'updated_at' => $timestamp,
            ];

            DB::table('academic_attendances')->where('slug', $validated['slug'])->update($updateData);

            DB::commit();

            $updatedAttendance = DB::table('academic_attendances')->where('slug', $validated['slug'])->first();

            return response()->json([
                'message' => 'Attendance updated successfully.',
                'data' => $updatedAttendance
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update attendance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                ->where('academic_attendances.attendee_slug', $validated['student_slug'])
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
                'data' => $attendances,
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
