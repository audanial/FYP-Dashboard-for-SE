<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Main behaviour ────────────────────────────────────────────────────────────

test('command links rows with exact supervisor_name match and leaves the rest unlinked', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Exact Person']);

    FypProject::factory()->count(2)->create(['supervisor_name' => 'Exact Person',      'supervisor_id' => null]);
    FypProject::factory()->create(        ['supervisor_name' => 'Fuzzy Person - Ts.', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Exact Person')->whereNull('supervisor_id')->count())->toBe(0)
        ->and(FypProject::where('supervisor_name', 'Exact Person')->where('supervisor_id', $sup->id)->count())->toBe(2)
        ->and(FypProject::where('supervisor_name', 'Fuzzy Person - Ts.')->whereNull('supervisor_id')->count())->toBe(1);
});

// ── Guard: role filter ────────────────────────────────────────────────────────

test('command does not link a row when the matching account is not a supervisor', function () {
    User::factory()->create(['role' => 'student', 'name' => 'Looks Like Sup']);

    FypProject::factory()->create(['supervisor_name' => 'Looks Like Sup', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Looks Like Sup')->whereNull('supervisor_id')->count())->toBe(1);
});

// ── Guard: idempotency ────────────────────────────────────────────────────────

test('command is idempotent and safe to run multiple times', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Idem Sup']);

    FypProject::factory()->create(['supervisor_name' => 'Idem Sup', 'supervisor_id' => null]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);
    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Idem Sup')->where('supervisor_id', $sup->id)->count())->toBe(1)
        ->and(FypProject::where('supervisor_name', 'Idem Sup')->whereNull('supervisor_id')->count())->toBe(0);
});

// ── Guard: already-linked rows are never touched ──────────────────────────────

test('command does not overwrite a supervisor_id that is already set', function () {
    $originalSup = User::factory()->create(['role' => 'supervisor', 'name' => 'Original Sup']);
    $otherSup    = User::factory()->create(['role' => 'supervisor', 'name' => 'Other Sup']);

    // Row is already linked to $originalSup but carries $otherSup's name (a
    // manual curation override). The command must not disturb the existing link.
    FypProject::factory()->create([
        'supervisor_name' => 'Other Sup',
        'supervisor_id'   => $originalSup->id,
    ]);

    $this->artisan('fyp:link-supervisors')->assertExitCode(0);

    expect(FypProject::where('supervisor_name', 'Other Sup')->value('supervisor_id'))->toBe($originalSup->id);
});
