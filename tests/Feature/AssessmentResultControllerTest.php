<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AssessmentResultControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_index_returns_successful_response()
    {
        $response = $this->getJson('/api/assessment-results');
        $response->assertStatus(200);
    }
}
