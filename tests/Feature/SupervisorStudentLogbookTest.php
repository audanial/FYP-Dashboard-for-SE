<?php

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;

test('supervisors can view logbooks for their own assigned students', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);
    $student = User::factory()->create([
        'role' => 'student',
        'username' => '522120000003',
        'name' => 'Student Three',
    ]);
    $otherStudent = User::factory()->create([
        'role' => 'student',
        'username' => '522120000004',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student Three',
        'student_id' => '522120000003',
        'title' => 'AI Attendance System',
        'domain' => 'AI',
        'application_type' => 'Web App',
        'fyp_phase' => 'FYP 1',
        'supervisor_name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    Logbook::factory()->create([
        'user_id' => $student->id,
        'title' => 'Week 1',
        'content' => 'Completed proposal draft.',
        'date' => '2026-04-01',
    ]);

    Logbook::factory()->create([
        'user_id' => $otherStudent->id,
        'title' => 'Week 2',
        'content' => 'Should not be visible.',
        'date' => '2026-04-02',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000003']))
        ->assertOk()
        ->assertSee('Student Logbook')
        ->assertSee('Student Three')
        ->assertSee('AI Attendance System')
        ->assertSee('Week 1')
        ->assertSee('Completed proposal draft.')
        ->assertDontSee('Should not be visible.');
});

test('supervisors cannot view logbooks for students not assigned to them', function () {
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'name' => 'Ts. Tiliza Binti Awang Mat',
    ]);

    FypProject::factory()->create([
        'student_name' => 'Student Four',
        'student_id' => '522120000004',
        'supervisor_name' => 'Dr. Someone Else',
    ]);

    $this->actingAs($supervisor);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000004']))
        ->assertNotFound();
});

test('students cannot access the supervisor student logbook page', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($student);

    $this->get(route('supervisor.students.logbook', ['studentId' => '522120000004']))
        ->assertForbidden();
});
