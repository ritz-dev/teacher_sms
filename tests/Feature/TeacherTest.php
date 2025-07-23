<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creation_and_relationship()
    {
        $teacher = Teacher::create([
            'slug' => (string) Str::uuid(),
            'personal_slug' => 'test-personal-slug',
            'teacher_name' => 'Jane Smith',
            'teacher_code' => 'TCH001',
            'email' => 'jane@example.com',
            'phone' => '0987654321',
            'address' => '456 Main St',
            'qualification' => 'MSc Mathematics',
            'subject' => 'Mathematics',
            'experience_years' => 10,
            'salary' => 60000,
            'hire_date' => now(),
            'status' => 'active',
            'employment_type' => 'Full-time',
        ]);

        $this->assertInstanceOf(Teacher::class, $teacher);
        $this->assertEquals('Jane Smith', $teacher->teacher_name);
        $this->assertEquals('TCH001', $teacher->teacher_code);
        $this->assertEquals('active', $teacher->status);
        $this->assertTrue(isset($teacher->slug));
    }

    public function test_teacher_soft_delete()
    {
        $teacher = Teacher::factory()->create();
        $teacher->delete();
        $this->assertSoftDeleted($teacher);
    }
}
