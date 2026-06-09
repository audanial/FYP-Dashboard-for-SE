<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/*
 * Phase 1 (RED) — encoding stability for supervisor_name on CSV import.
 *
 * Reproduces two real defects visible in the production data
 * (e.g. "Rohaya Abu Hassan <U+FFFD> Ts."), plus the one-time repair tool:
 *
 *   1. A UTF-8 BOM on the file attaches to the first header ("Group"),
 *      which then fails to resolve, so pair_number silently stops mapping.
 *   2. A non-UTF-8 (Windows-1252) byte inside a name is stored verbatim
 *      instead of being converted to clean UTF-8.
 *   3. `fyp:clean-supervisor-encoding` must repair rows already stored mojibake.
 *
 * Expected to FAIL until Phase 1 GREEN (parser BOM-strip + charset
 * conversion + the repair command). The 13 existing CSV tests are the
 * regression guard and are NOT touched by this file.
 */

// Distinct name so it never collides with the global helper in CsvImportRealFormatTest.
function runEncodingImport(string $content, string $filename = 'encoding-sample.csv')
{
    $coordinator = User::factory()->create(['role' => 'coordinator']);
    Auth::login($coordinator);

    $file = UploadedFile::fake()->createWithContent($filename, $content);

    return Livewire::test('fyp-projects')
        ->set('phase', 'FYP 1')
        ->set('semester', 'MARCH 2026')
        ->set('csvFile', $file)
        ->call('importCsv');
}

test('a UTF-8 BOM on the header row does not break Group to pair_number mapping', function () {
    // Identical to the known-good real sample, but with a UTF-8 BOM prefix.
    $content = "\xEF\xBB\xBF" . file_get_contents(base_path('tests/Files/real-sample.csv'));

    runEncodingImport($content);

    // With a BOM, header[0] becomes "\u{FEFF}Group" and fails to resolve, so
    // pair_number stops forward-filling. Mirrors the non-BOM import expectation.
    expect(FypProject::where('student_id', 'S001')->value('pair_number'))->toBe(1)
        ->and(FypProject::where('student_id', 'S002')->value('pair_number'))->toBe(2)
        ->and(FypProject::where('student_id', 'S003')->value('pair_number'))->toBe(2);
});

test('a Windows-1252 byte in a supervisor name is stored as clean UTF-8', function () {
    // 0x96 is the Windows-1252 en dash; the real CSV carried these between the
    // name and the academic suffix, e.g. "Rohaya Abu Hassan \x96 Ts.".
    $content = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n"
        . "1,1,E001,EVE NG,Mobile Thing,0111,Rohaya Abu Hassan \x96 Ts.,Dr Wong,,,\n";

    runEncodingImport($content);

    // After Phase 1 the stored name must be valid UTF-8 with a real en dash,
    // not the raw 0x96 byte (which renders as the replacement character).
    expect(FypProject::where('student_id', 'E001')->value('supervisor_name'))
        ->toBe("Rohaya Abu Hassan \u{2013} Ts.");
});

test('fyp:clean-supervisor-encoding repairs an already-stored mojibake name', function () {
    $project = FypProject::factory()->create([
        'supervisor_name' => "Rohaya Abu Hassan \x96 Ts.",
    ]);

    $this->artisan('fyp:clean-supervisor-encoding')->assertExitCode(0);

    expect($project->fresh()->supervisor_name)->toBe("Rohaya Abu Hassan \u{2013} Ts.");
});
