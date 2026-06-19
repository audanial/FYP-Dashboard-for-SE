<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Minimal CSV with the standard real-format headers used by the importer.
function csvRowFor(string $studentId, string $supervisorName): string
{
    $header = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n";
    $row    = "1,1,{$studentId},Test Student,Test Title,0111,{$supervisorName},Dr Siti,,Web App,\n";

    return $header . $row;
}

function runImport(string $csvContent): void
{
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    Auth::login($coordinator);

    $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    Livewire::test('fyp-projects')
        ->set('phase', 'FYP 1')
        ->set('semester', 'MARCH 2026')
        ->set('csvFile', $file)
        ->call('importCsv');
}

// ── Main feature ──────────────────────────────────────────────────────────────

test('importing a row whose supervisor_name exactly matches a supervisor account sets supervisor_id', function () {
    $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Exact Match Sup']);

    runImport(csvRowFor('IMP001', 'Exact Match Sup'));

    $project = FypProject::where('student_id', 'IMP001')->first();
    expect($project)->not->toBeNull()
        ->and($project->supervisor_id)->toBe($sup->id);
});

// ── Guard tests ───────────────────────────────────────────────────────────────

test('importing a row whose supervisor_name has no exact account match leaves supervisor_id null', function () {
    User::factory()->create(['role' => 'supervisor', 'name' => 'Some Other Supervisor']);

    runImport(csvRowFor('IMP002', 'No Such Supervisor - Ts.'));

    $project = FypProject::where('student_id', 'IMP002')->first();
    expect($project)->not->toBeNull()
        ->and($project->supervisor_id)->toBeNull();
});

test('a non-supervisor account with an exactly matching name is not used to set supervisor_id', function () {
    // A student account happens to share the CSV supervisor name — must be ignored.
    User::factory()->create(['role' => 'student', 'name' => 'Looks Like Sup']);

    runImport(csvRowFor('IMP003', 'Looks Like Sup'));

    $project = FypProject::where('student_id', 'IMP003')->first();
    expect($project)->not->toBeNull()
        ->and($project->supervisor_id)->toBeNull();
});
