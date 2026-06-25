<?php

use App\Models\FypProject;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\SupervisorName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Item 6: the FYP import links a project to a supervisor by normalizing the
 * messy SUPERVISOR column to a slug and matching it against supervisors.name_slug.
 * On a match it sets fyp_projects.supervisor_id to the roster row's user_id.
 * On no match it logs the raw string and skips linking (the import still succeeds).
 */

function rosterCsvRow(string $studentId, string $supervisorName): string
{
    $header = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n";
    $row    = "1,1,{$studentId},Test Student,Test Title,0111,{$supervisorName},Dr Siti,,Web App,\n";

    return $header . $row;
}

function runRosterImport(string $csvContent): void
{
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    Auth::login($coordinator);

    $file = UploadedFile::fake()->createWithContent('roster.csv', $csvContent);

    Livewire::test('fyp-projects')
        ->set('phase', 'FYP 2')
        ->set('semester', 'OCTOBER 2025')
        ->set('csvFile', $file)
        ->call('importCsv');
}

function seedRosterSupervisor(string $name, string $email): User
{
    $user = User::factory()->create(['role' => 'supervisor', 'name' => $name]);

    Supervisor::create([
        'name'      => $name,
        'name_slug' => SupervisorName::slug($name),
        'email'     => $email,
        'user_id'   => $user->id,
        'confirmed' => true,
    ]);

    return $user;
}

test('a messy supervisor string links to the roster row by normalized slug', function () {
    $user = seedRosterSupervisor('Tiliza Awang Mat', 'tiliza@unikl.edu.my');

    // The CSV carries titles/particles the roster name does not.
    runRosterImport(rosterCsvRow('R001', 'Tiliza binti Awang Mat - Ts.'));

    $project = FypProject::where('student_id', 'R001')->first();
    expect($project)->not->toBeNull()
        ->and($project->supervisor_id)->toBe($user->id);
});

test('an unmatched supervisor leaves supervisor_id null and the import still succeeds', function () {
    seedRosterSupervisor('Tiliza Awang Mat', 'tiliza@unikl.edu.my');

    runRosterImport(rosterCsvRow('R002', 'Someone Not In The Roster'));

    $project = FypProject::where('student_id', 'R002')->first();
    expect($project)->not->toBeNull()
        ->and($project->supervisor_id)->toBeNull();
});

test('an unmatched supervisor string is logged for curation', function () {
    Log::spy();
    seedRosterSupervisor('Tiliza Awang Mat', 'tiliza@unikl.edu.my');

    runRosterImport(rosterCsvRow('R003', 'Ghost Lecturer'));

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) =>
            str_contains($message, 'Unmatched supervisor')
            && ($context['raw'] ?? null) === 'Ghost Lecturer')
        ->once();
});
