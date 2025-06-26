<?php

namespace App\Http\Controllers\APIs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class AssessmentController extends Controller
{

    public function index (Request $request)
    {
        try {
            $validated = $request->validate([
                'teacher_slug' => ['nullable', 'string', 'exists:teachers,slug'],
                'academic_class_section_slug' => ['nullable', 'string', 'exists:academic_class_sections,slug'],
                'subject_slug' => ['nullable', 'string', 'exists:subjects,slug'],
                'type' => ['nullable', 'in:quiz,test,exam,assignment'],
                'is_published' => ['nullable', 'boolean'],
                'date' => ['nullable', 'date'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'limit' => ['nullable', 'integer', 'min:1'],
                'skip' => ['nullable', 'integer', 'min:0'],
            ]);

            $query = DB::table('assessments as as')
                ->join('subjects as sub', 'as.subject_slug', '=', 'sub.slug')
                ->join('academic_class_sections as acs', 'as.academic_class_section_slug', '=', 'acs.slug')    
                ->when(!empty($validated['teacher_slug']), function ($q) use ($validated) {
                    $q->where('teacher_slug', $validated['teacher_slug']);
                })
                ->when(!empty($validated['academic_class_section_slug']), function ($q) use ($validated) {
                    $q->where('academic_class_section_slug', $validated['academic_class_section_slug']);
                })
                ->when(!empty($validated['subject_slug']), function ($q) use ($validated) {
                    $q->where('subject_slug', $validated['subject_slug']);
                })
                ->when(!empty($validated['type']), function ($q) use ($validated) {
                    $q->where('type', $validated['type']);
                })
                ->when(isset($validated['is_published']), function ($q) use ($validated) {
                    $q->where('is_published', $validated['is_published']);
                })
                ->when(!empty($validated['date']), function ($q) use ($validated) {
                    $q->where('date', $validated['date']);
                })
                ->when(!empty($validated['start_date']) && !empty($validated['end_date']), function ($q) use ($validated) {
                    $q->whereBetween('date', [
                        Carbon::parse($validated['start_date'])->format('Ymd'),
                        Carbon::parse($validated['end_date'])->format('Ymd')
                    ]);
                })
                ->when(!empty($validated['start_date']) && empty($validated['end_date']), function ($q) use ($validated) {
                    $q->where('date', '>=', Carbon::parse($validated['start_date'])->format('Ymd'));
                })
                ->when(!empty($validated['end_date']) && empty($validated['start_date']), function ($q) use ($validated) {
                    $q->where('date', '<=', Carbon::parse($validated['end_date'])->format('Ymd'));
                })
                ->select(
                    'as.slug as slug',
                    'as.title as title',
                    'as.teacher_slug as teacher_slug',
                    'as.academic_class_section_slug as academic_class_section_slug',
                    'as.subject_slug as subject_slug',
                    'sub.name as subject_name',
                    'as.type as type',
                    'as.date as date',
                    'as.due_date as due_date',
                    'as.max_marks as max_marks',
                    'as.min_marks as min_marks',
                    'as.description as description',
                    'as.is_published as is_published',
                )
                ->orderByDesc('date');

            $total = (clone $query)->count();

            if (!empty($validated['skip'])) {
                $query->skip($validated['skip']);
            }
            if (!empty($validated['limit'])) {
                $query->take($validated['limit']);
            }

            $results = $query->get();

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

    public function getAssessmentsByStudent(Request $request)
    {
        try {

        $validated = $request->validate([
                'student_slug' => ['nullable', 'string'],
            ]);

        $assessments = DB::table('assessment_results as asr')
            ->join('assessments as', 'asr.assessment_slug', '=', 'as.slug')
            ->where('assessment_results.student_slug', $validated['student_slug'])
            ->select(
                'as.slug as assessment_slug',
                'as.name as assessment_name',
            )
            ->get();

        return response()->json([
            'status' => 'OK',
            'data' => $assessments
        ]);
        
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assessments for student.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $assessment = DB::table('assessments')->where('slug', $slug)->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $assessment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'owner_slug' => 'required|string|max:255',
                'title' => 'required|string',
                'academic_class_section_slug' => 'required|exists:academic_class_sections,slug',
                'subject_slug' => 'required|exists:subjects,slug',
                'type' => 'required|in:quiz,test,exam,assignment',
                'date' => 'required|date', // Now expects an integer (timestamp)
                'due_date' => 'nullable|date',
                'max_marks' => 'required|integer',
                'min_marks' => 'required|integer',
                'description' => 'nullable|string',
                'is_published' => 'boolean',
            ]);

            $validated['date']  = (int) Carbon::parse($validated['date'])->format('Ymd');
            $validated['due_date'] = (int) Carbon::parse($validated['due_date'])->format('Ymd');

            $id = DB::table('assessments')->insertGetId([
                'slug' => (string) Str::uuid(),
                'title' => $validated['title'],
                'academic_class_section_slug' => $validated['academic_class_section_slug'],
                'teacher_slug' => $validated['owner_slug'],
                'subject_slug' => $validated['subject_slug'],
                'type' => $validated['type'],
                'date' => $validated['date'],
                'due_date' => $validated['due_date'],
                'max_marks' => $validated['max_marks'],
                'min_marks' => $validated['min_marks'],
                'description' => $validated['description'] ?? null,
                'is_published' => $validated['is_published'] ?? false,
            ]);

            $assessment = DB::table('assessments')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Assessment created successfully.',
                'data' => $assessment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|exists:assessments,slug',
                'title' => 'required|string',
                'academic_class_section_slug' => 'required|exists:academic_class_sections,slug',
                'subject_slug' => 'required|exists:subjects,slug',
                'type' => 'required|in:quiz,test,exam,assignment',
                'date' => 'required|date', // Now expects an integer (timestamp)
                'due_date' => 'nullable|date',
                'max_marks' => 'required|integer',
                'min_marks' => 'required|integer',
                'description' => 'nullable|string',
                'is_published' => 'boolean',
            ]);

            $validated['date']  = (int) Carbon::parse($validated['date'])->format('Ymd');
            $validated['due_date'] = (int) Carbon::parse($validated['due_date'])->format('Ymd');

            DB::table('assessments')->where('slug', $validated['slug'])->update([
                'title' => $validated['title'],
                'academic_class_section_slug' => $validated['academic_class_section_slug'],
                'subject_slug' => $validated['subject_slug'],
                'type' => $validated['type'],
                'date' => $validated['date'],
                'due_date' => $validated['due_date'],
                'max_marks' => $validated['max_marks'],
                'min_marks' => $validated['min_marks'],
                'description' => $validated['description'] ?? null,
                'is_published' => $validated['is_published'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assessment updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assessment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|exists:assessments,slug',
            ]);

            DB::table('assessments')->where('slug', $validated['slug'])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
