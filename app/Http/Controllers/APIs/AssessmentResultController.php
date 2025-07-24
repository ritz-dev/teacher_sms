<?php

namespace App\Http\Controllers\APIs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class AssessmentResultController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'assessment_slug' => ['nullable', 'string', 'exists:assessments,slug'],
                'student_slug' => ['nullable', 'string'],
                'status' => ['nullable', 'in:pending,graded,reviewed'],
                'limit' => ['nullable', 'integer', 'min:1'],
                'skip' => ['nullable', 'integer', 'min:0'],
            ]);

            $query = DB::table('assessment_results')
                ->join('assessments', 'assessment_results.assessment_slug', '=', 'assessments.slug')
                ->join('subjects', 'assessments.subject_slug', '=', 'subjects.slug')
                ->join('students','assessments.student_slug','=','students.slug')
                ->when(!empty($validated['assessment_slug']), fn($q) => $q->where('assessment_slug', $validated['assessment_slug']))
                ->when(!empty($validated['student_slug']), fn($q) => $q->where('student_slug', $validated['student_slug']))
                ->when(!empty($validated['status']), fn($q) => $q->where('status', $validated['status']))
                ->select(
                    'assessment_results.slug as slug',
                    'assessment_results.assessment_slug as assessment_slug',
                    'assessment_results.student_slug as student_slug',
                    'students.student_name as student_name',
                    'students.roll_number as roll_number',
                    'assessment_results.marks_obtained as marks_obtained',
                    'assessment_results.remarks as remarks',
                    'assessment_results.status as status',
                    'assessment_results.graded_by as graded_by',
                    'assessment_results.graded_at as graded_at',
                    'assessments.title as assessment_name',
                    'subjects.name as subject_name',
                );

            $total = (clone $query)->count();

            if (!empty($validated['skip'])) $query->skip($validated['skip']);
            if (!empty($validated['limit'])) $query->take($validated['limit']);

            $results = $query->get();

            return response()->json([
                'success' => true,
                'total' => $total,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assessment results.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $result = DB::table('assessment_results')->where('slug', $slug)->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment result not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment result.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getResultsByStudent(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_slug' => 'required|string',
                'assessments_slug' => 'required|array',
                'assessments_slug.*' => 'string|exists:assessments,slug',
            ]);

            $results = DB::table('assessment_results')
                    ->join('assessments', 'assessment_results.assessment_slug', '=', 'assessments.slug')
                    ->join('subjects', 'assessments.subject_slug', '=', 'subjects.slug')
                    ->where('student_slug', $validated['student_slug'])
                    ->whereIn('assessment_slug', $validated['assessments_slug'])
                    ->select(
                        'assessment_results.slug as slug',
                        'assessment_results.assessment_slug as assessment_slug',
                        'assessment_results.student_slug as student_slug',
                        'assessment_results.marks_obtained as marks_obtained',
                        'assessment_results.remarks as remarks',
                        'assessment_results.status as status',
                        'assessment_results.graded_by as graded_by',
                        'assessment_results.graded_at as graded_at',
                        'assessments.title as assessment_name',
                        'subjects.name as subject_name',
                    )->get();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assessment results for student.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'assessment_slug' => 'required|string|exists:assessments,slug',
                'student_slug' => 'required|string',
                'marks_obtained' => 'nullable|integer',
                'remarks' => 'nullable|string',
                'status' => 'nullable|in:pending,graded,reviewed',
                'graded_by' => 'nullable|string',
                'graded_at' => 'nullable|date',
            ]);

            $slug = (string) Str::uuid();

            $id = DB::table('assessment_results')->insertGetId([
                'slug' => $slug,
                'assessment_slug' => $validated['assessment_slug'],
                'student_slug' => $validated['student_slug'],
                'marks_obtained' => $validated['marks_obtained'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'graded_by' => $validated['graded_by'] ?? null,
                'graded_at' => isset($validated['graded_at']) ? Carbon::parse($validated['graded_at']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result = DB::table('assessment_results')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Assessment result created successfully.',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment result.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|exists:assessment_results,slug',
                'marks_obtained' => 'nullable|integer',
                'remarks' => 'nullable|string',
                'status' => 'nullable|in:pending,graded,reviewed',
                'graded_by' => 'nullable|string',
                'graded_at' => 'nullable|date',
            ]);

            $updateData = [
                'marks_obtained' => $validated['marks_obtained'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'] ?? null,
                'graded_by' => $validated['graded_by'] ?? null,
                'graded_at' => isset($validated['graded_at']) ? Carbon::parse($validated['graded_at']) : null,
                'updated_at' => now(),
            ];

            // Remove null values to avoid overwriting with null
            $updateData = array_filter($updateData, fn($v) => !is_null($v));

            DB::table('assessment_results')->where('slug', $validated['slug'])->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Assessment result updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assessment result.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|exists:assessment_results,slug',
            ]);

            DB::table('assessment_results')->where('slug', $validated['slug'])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment result deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment result.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
