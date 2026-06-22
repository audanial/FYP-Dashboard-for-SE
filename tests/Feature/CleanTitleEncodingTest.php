<?php

use App\Models\FypProject;

test('fyp:clean-title-encoding repairs a title with a stored Windows-1252 byte', function () {
    $project = FypProject::factory()->create([
        'title' => "Design and Development of an Integrated Caf\xe9 Management Platform for Cafe Nissa",
    ]);

    $this->artisan('fyp:clean-title-encoding')->assertExitCode(0);

    expect($project->fresh()->title)
        ->toBe("Design and Development of an Integrated Café Management Platform for Cafe Nissa");
});

test('fyp:clean-title-encoding leaves a clean UTF-8 title untouched', function () {
    $project = FypProject::factory()->create([
        'title' => 'Design and Development of an Integrated Café Management Platform for Cafe Nissa',
    ]);

    $this->artisan('fyp:clean-title-encoding')->assertExitCode(0);

    expect($project->fresh()->title)
        ->toBe('Design and Development of an Integrated Café Management Platform for Cafe Nissa');
});

test('fyp:clean-title-encoding reports the correct repaired count', function () {
    FypProject::factory()->create(['title' => "Caf\xe9 App"]);
    FypProject::factory()->create(['title' => 'Clean Title']);

    $this->artisan('fyp:clean-title-encoding')
        ->assertExitCode(0)
        ->expectsOutputToContain('Repaired 1');
});
