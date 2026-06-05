<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/*
 * Import-behaviour tests for the REAL supervisor CSV format.
 *
 * All of these are expected to FAIL until the importer is updated
 * (Steps 3–7 of the plan): proper fgetcsv parsing, FYP TITLE mapping,
 * forward-fill Group -> pair_number, lead-to-partner metadata fill-down,
 * blank-student skipping, and the is_ifyp / PLATFORM mappings.
 *
 * As-is, the import aborts on the unmapped "FYP TITLE" header, so no
 * records are created and every positive assertion below fails.
 */

function importCsvContent(string $content, string $filename = 'real-sample.csv')
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

function importRealSample()
{
    return importCsvContent(file_get_contents(base_path('tests/Files/real-sample.csv')));
}

test('FYP TITLE column is imported as the project title', function () {
    importRealSample();

    $alice = FypProject::where('student_id', 'S001')->first();

    expect($alice?->title)->toBe('Solo Project One');
});

test('Group column is forward-filled into pair_number for both students', function () {
    importRealSample();

    // Lead with an explicit Group number
    expect(FypProject::where('student_id', 'S002')->value('pair_number'))->toBe(2);
    // Partner row had a BLANK Group -> must inherit the previous Group (2)
    expect(FypProject::where('student_id', 'S003')->value('pair_number'))->toBe(2);
    // Solo student keeps its own Group number
    expect(FypProject::where('student_id', 'S001')->value('pair_number'))->toBe(1);
});

test('multiline quoted title does not break column alignment', function () {
    importRealSample();

    $bob = FypProject::where('student_id', 'S002')->first();

    // If the multiline field shifted columns, supervisor/assessor would be wrong.
    expect($bob?->supervisor_name)->toBe('Dr Ahmad')
        ->and($bob?->assessor_name)->toBe('Dr Siti')
        ->and($bob?->title)->toContain('Smart Parking')
        ->and($bob?->title)->toContain('Management System');
});

test('partner row inherits project metadata from the lead row', function () {
    importRealSample();

    $bob = FypProject::where('student_id', 'S002')->first();
    $cara = FypProject::where('student_id', 'S003')->first();

    expect($cara?->title)->toBe($bob?->title)
        ->and($cara?->supervisor_name)->toBe('Dr Ahmad')
        ->and($cara?->assessor_name)->toBe('Dr Siti');
});

test('studentless lead row is skipped and does not create a fake project', function () {
    importRealSample();

    // No record may be created without a real student id.
    expect(FypProject::where('student_id', '')->count())->toBe(0)
        ->and(FypProject::whereNull('student_id')->count())->toBe(0);

    // Exactly the four real students are imported (the studentless lead is skipped).
    expect(FypProject::count())->toBe(4);
});

test('Phish Guard title is preserved on the real student record', function () {
    importRealSample();

    // The title lived on the studentless lead row; it must fill down to the
    // real partner student (DAVE) instead of being lost.
    $dave = FypProject::where('student_id', 'S005')->first();

    expect($dave?->title)->toBe('Phish Guard System')
        ->and($dave?->pair_number)->toBe(3);
});

test('empty Domain / Platform / Type default safely', function () {
    importRealSample();

    $alice = FypProject::where('student_id', 'S001')->first();

    expect($alice?->domain)->toBe('Others')
        ->and($alice?->application_type)->toBe('Web App')
        ->and((bool) $alice?->is_ifyp)->toBeFalse();
});

test('PLATFORM column value maps through to application_type', function () {
    $content = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n"
        . "1,1,P001,EVE NG,Mobile Thing,0111,Dr Lim,Dr Wong,,Mobile App,\n";

    importCsvContent($content);

    expect(FypProject::where('student_id', 'P001')->value('application_type'))->toBe('Mobile App');
});

test('TYPE column value maps through to is_ifyp', function () {
    $content = "Group,No.,Student ID,STUDENT NAME,FYP TITLE,CONTACT NO.,SUPERVISOR,ASSESSOR,DOMAIN,PLATFORM ,TYPE\n"
        . "1,1,T001,FAY OH,Industry Thing,0111,Dr Lim,Dr Wong,,,Industrial\n"
        . "2,2,T002,GUS AL,Regular Thing,0222,Dr Lim,Dr Wong,,,Regular\n";

    importCsvContent($content);

    expect((bool) FypProject::where('student_id', 'T001')->value('is_ifyp'))->toBeTrue()
        ->and((bool) FypProject::where('student_id', 'T002')->value('is_ifyp'))->toBeFalse();
});
