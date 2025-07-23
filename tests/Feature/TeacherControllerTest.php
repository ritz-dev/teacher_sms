<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeacherControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_index_returns_successful_response()
    {
        $response = $this->getJson('/api/teachers');
        $response->assertStatus(200);
    }
}
