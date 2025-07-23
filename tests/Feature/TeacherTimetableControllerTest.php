<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeacherTimetableControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_index_returns_successful_response()
    {
        $response = $this->getJson('/api/teacher-timetable');
        $response->assertStatus(200);
    }
}
