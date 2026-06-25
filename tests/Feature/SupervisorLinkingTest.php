<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forSupervisor factory state links id and copies the canonical name', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Dr Canonical']);

    $project = FypProject::factory()->forSupervisor($sup)->create();

    expect($project->supervisor_id)->toBe($sup->id)
        ->and($project->supervisor_name)->toBe('Dr Canonical');
});
