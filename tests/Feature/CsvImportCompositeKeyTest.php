<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Item 1 + 2 (composite identity key): a student is uniquely identified by
 * student_id + semester + fyp_phase, not student_id alone. A student who
 * progresses FYP1 -> FYP2 (or appears in two semesters) must produce two rows,
 * while a true same-semester+phase re-import must still be skipped.
 */

// Distinct helper names so they never collide with the global import helpers
// defined in the other CSV import test files.
function compositeKeyCsvRow(string $studentId): string
{
    $header = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n";
    $row    = "1,1,{$studentId},Test Student,Test Title,0111,Dr Test,Dr Siti,,Web App,\n";

    return $header . $row;
}

function runImportFor(string $csvContent, string $phase, string $semester): void
{
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    Auth::login($coordinator);

    $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    Livewire::test('fyp-projects')
        ->set('phase', $phase)
        ->set('semester', $semester)
        ->set('csvFile', $file)
        ->call('importCsv');
}

test('the same student can be imported into two different semester and phase combinations', function () {
    // Same person, consecutive stages: FYP1 Oct 2025 -> FYP2 March 2026.
    runImportFor(compositeKeyCsvRow('S900'), 'FYP 1', 'OCTOBER 2025');
    runImportFor(compositeKeyCsvRow('S900'), 'FYP 2', 'MARCH 2026');

    // Both records must exist — the second import must NOT be skipped.
    expect(FypProject::where('student_id', 'S900')->count())->toBe(2)
        ->and(FypProject::where('student_id', 'S900')
            ->where('semester', 'OCTOBER 2025')->where('fyp_phase', 'FYP 1')->exists())->toBeTrue()
        ->and(FypProject::where('student_id', 'S900')
            ->where('semester', 'MARCH 2026')->where('fyp_phase', 'FYP 2')->exists())->toBeTrue();
});

test('re-importing the same student into the identical semester and phase is still skipped as a duplicate', function () {
    // Genuine duplicate (same file imported twice) must NOT create a second row.
    runImportFor(compositeKeyCsvRow('S901'), 'FYP 2', 'MARCH 2026');
    runImportFor(compositeKeyCsvRow('S901'), 'FYP 2', 'MARCH 2026');

    expect(FypProject::where('student_id', 'S901')->count())->toBe(1);
});
