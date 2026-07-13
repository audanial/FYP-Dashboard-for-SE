<?php

use App\Models\FypProject;
use App\Models\User;
use Livewire\Livewire;

test('supervisors can access my students page and only see their assigned projects', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student One',
        'student_id' => '522120000001',
        'title' => 'AI Attendance System',
        'domain' => 'AI',
        'app_type' => 'Web App',
        'fyp_phase' => 'FYP 1',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student Two',
        'student_id' => '522120000002',
        'title' => 'Medical Tracker',
        'supervisor_name' => 'Dr. Someone Else',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students'))
        ->assertOk()
        ->assertSee('Student One')
        ->assertSee('522120000001')
        ->assertSee('AI Attendance System')
        ->assertSee('AI')
        ->assertSee('Web App')
        ->assertSee('FYP 1')
        ->assertSee(route('supervisor.students.logbook', ['studentId' => '522120000001']), false)
        ->assertDontSee('Student Two');
});

test('students cannot access the supervisor students page', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($student);

    $this->get(route('supervisor.students'))->assertForbidden();
});

test('supervisors can search their assigned students by name or student id', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Alice Tan',
        'student_id' => '522120000011',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Bob Lee',
        'student_id' => '522120000012',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    $this->actingAs($supervisor);

    Livewire::test('my-students')
        ->set('search', 'Alice')
        ->assertSee('Alice Tan')
        ->assertDontSee('Bob Lee')
        ->set('search', '522120000012')
        ->assertSee('Bob Lee')
        ->assertDontSee('Alice Tan');
});

test('student search is case-insensitive', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Alice Tan',
        'student_id' => '522120000021',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    $this->actingAs($supervisor);

    Livewire::test('my-students')
        ->set('search', 'alice')
        ->assertSee('Alice Tan');
});

test('supervisors see assigned students paginated to ten per page', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    foreach (range(1, 11) as $number) {
        FypProject::factory()->create([
            'student_name' => sprintf('Student %02d', $number),
            'student_id' => '5221200000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
        ]);
    }

    $this->actingAs($supervisor);

    Livewire::test('my-students')
        ->assertSee('Student 01')
        ->assertSee('Student 10')
        ->assertDontSee('Student 11')
        ->call('gotoPage', 2)
        ->assertSee('Student 11')
        ->assertDontSee('Student 01');
});
