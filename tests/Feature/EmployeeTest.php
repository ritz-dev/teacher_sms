<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creation_and_relationship()
    {
        $employee = Employee::create([
            'slug' => (string) Str::uuid(),
            'personal_slug' => 'test-personal-slug',
            'employee_name' => 'John Doe',
            'employee_code' => 'EMP001',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'position' => 'Teacher',
            'department' => 'Science',
            'employment_type' => 'Full-time',
            'hire_date' => now(),
            'resign_date' => null,
            'experience_years' => 5,
            'salary' => 50000,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertEquals('John Doe', $employee->employee_name);
        $this->assertEquals('EMP001', $employee->employee_code);
        $this->assertEquals('active', $employee->status);
        $this->assertTrue(isset($employee->slug));
    }

    public function test_employee_soft_delete()
    {
        $employee = Employee::factory()->create();
        $employee->delete();
        $this->assertSoftDeleted($employee);
    }
}
