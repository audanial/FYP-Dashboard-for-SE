<?php

use App\Services\CsvHeaderResolver;

/*
 * These tests assert that the REAL supervisor CSV headers resolve to the
 * correct database columns. They are expected to FAIL until the header
 * mappings in config/csv_mappings.php are updated (Step 3 of the plan).
 *
 * Real header row (note the trailing space on "PLATFORM "):
 *   [0] Group  [1] No.  [2] Student ID  [3] STUDENT NAME  [4] FYP TITLE
 *   [5] CONTACT NO.  [6] SUPERVISOR  [7] ASSESSOR  [8] DOMAIN  [9] PLATFORM   [10] TYPE
 */

function realHeaderRow(): array
{
    return [
        'Group', 'No.', 'Student ID', 'STUDENT NAME', 'FYP TITLE',
        'CONTACT NO.', 'SUPERVISOR', 'ASSESSOR', 'DOMAIN', 'PLATFORM ', 'TYPE',
    ];
}

test('FYP TITLE header maps to title', function () {
    $map = app(CsvHeaderResolver::class)->resolve(realHeaderRow());

    expect($map['title'] ?? null)->toBe(4);
});

test('Group header maps to pair_number', function () {
    $map = app(CsvHeaderResolver::class)->resolve(realHeaderRow());

    expect($map['pair_number'] ?? null)->toBe(0);
});

test('PLATFORM header (with trailing space) maps to application_type', function () {
    $map = app(CsvHeaderResolver::class)->resolve(realHeaderRow());

    expect($map['application_type'] ?? null)->toBe(9);
});

test('TYPE header maps to is_ifyp', function () {
    $map = app(CsvHeaderResolver::class)->resolve(realHeaderRow());

    expect($map['is_ifyp'] ?? null)->toBe(10);
});
