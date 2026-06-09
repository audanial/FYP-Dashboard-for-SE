<?php

use App\Models\FypProject;
use App\Models\User;

/*
 * Phase 2 — supervisor_id foundation (structural only, zero behaviour change).
 * Proves the new FK + Eloquent relations work in both directions and that the
 * column is nullable with no effect on existing rows.
 */

test('a project belongs to its supervisor via supervisor_id', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    $project = FypProject::factory()->create(['supervisor_id' => $supervisor->id]);

    expect($project->supervisor)->not->toBeNull()
        ->and($project->supervisor->id)->toBe($supervisor->id);
});

test('a supervisor has many supervised projects via supervisor_id', function () {
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    FypProject::factory()->count(2)->create(['supervisor_id' => $supervisor->id]);
    FypProject::factory()->create(['supervisor_id' => null]); // unlinked, must not count

    expect($supervisor->supervisedProjects)->toHaveCount(2);
});

test('supervisor_id is nullable and defaults to null', function () {
    $project = FypProject::factory()->create();

    expect($project->supervisor_id)->toBeNull()
        ->and($project->supervisor)->toBeNull();
});
