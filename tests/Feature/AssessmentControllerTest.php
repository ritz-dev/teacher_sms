<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AssessmentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_index_returns_successful_response()
    {
        $response = $this->getJson('/api/assessments');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'total',
            'data',
        ]);
    }

    public function test_get_assessments_by_student_requires_student_slug()
    {
        $response = $this->postJson('/api/assessments/by-student', []);
        $response->assertStatus(422);
    }

    public function test_show_returns_not_found_for_invalid_slug()
    {
        $response = $this->getJson('/api/assessments/invalid-slug');
        $response->assertStatus(404);
    }

    public function test_store_requires_fields()
    {
        $response = $this->postJson('/api/assessments', []);
        $response->assertStatus(422);
    }

    public function test_update_requires_slug()
    {
        $response = $this->putJson('/api/assessments', []);
        $response->assertStatus(422);
    }

    public function test_delete_requires_slug()
    {
        $response = $this->deleteJson('/api/assessments', []);
        $response->assertStatus(422);
    }
}
