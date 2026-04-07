<?php

use App\Models\Logbook;
use App\Models\User;
use Livewire\Livewire;

test('students can access the logbook page and only see their own entries', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);
    $otherStudent = User::factory()->create([
        'role' => 'student',
    ]);

    $ownEntry = Logbook::factory()->create([
        'user_id' => $student->id,
        'title' => 'My own entry',
    ]);
    Logbook::factory()->create([
        'user_id' => $otherStudent->id,
        'title' => 'Another student entry',
    ]);

    $this->actingAs($student);

    $this->get(route('student.logbook'))
        ->assertOk()
        ->assertSee($ownEntry->title)
        ->assertDontSee('Another student entry')
        ->assertSee(route('student.logbook'), false);
});

test('coordinators cannot access the student logbook page', function () {
    $user = User::factory()->create([
        'role' => 'coordinator',
    ]);

    $this->actingAs($user);

    $this->get(route('student.logbook'))->assertForbidden();
});

test('students can create logbook entries', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $this->actingAs($student);

    Livewire::test('my-logbook')
        ->set('title', 'Weekly Progress')
        ->set('content', 'Completed the literature review and updated the draft.')
        ->set('date', '2026-04-07')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('logbooks', [
        'user_id' => $student->id,
        'title' => 'Weekly Progress',
        'date' => '2026-04-07 00:00:00',
    ]);
});

test('students can edit only their own logbook entries', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);
    $entry = Logbook::factory()->create([
        'user_id' => $student->id,
        'title' => 'Initial title',
        'content' => 'Initial content',
        'date' => '2026-04-06',
    ]);

    $this->actingAs($student);

    Livewire::test('my-logbook')
        ->call('editEntry', $entry->id)
        ->set('title', 'Updated title')
        ->set('content', 'Updated content')
        ->set('date', '2026-04-07')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('logbooks', [
        'id' => $entry->id,
        'user_id' => $student->id,
        'title' => 'Updated title',
        'content' => 'Updated content',
        'date' => '2026-04-07 00:00:00',
    ]);
});

test('students can delete their own logbook entries', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);
    $entry = Logbook::factory()->create([
        'user_id' => $student->id,
    ]);

    $this->actingAs($student);

    Livewire::test('my-logbook')
        ->call('deleteEntry', $entry->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('logbooks', [
        'id' => $entry->id,
    ]);
});
