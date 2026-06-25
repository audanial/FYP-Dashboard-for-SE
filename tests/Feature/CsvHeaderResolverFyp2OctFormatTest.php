<?php

use App\Services\CsvHeaderResolver;

/*
 * Item 3: the FYP2 OCTOBER 2025 file differs from the FYP1 MARCH 2026 format in
 * two ways:
 *   1. Column A is "Seat" instead of "Group".
 *   2. There is no "CONTACT NO." column, so every column after FYP TITLE shifts
 *      one position to the left.
 *
 * Real header row:
 *   [0] Seat  [1] No.  [2] Student ID  [3] STUDENT NAME  [4] FYP TITLE
 *   [5] SUPERVISOR  [6] ASSESSOR  [7] DOMAIN  [8] PLATFORM   [9] TYPE
 */

function fyp2OctHeaderRow(): array
{
    return [
        'Seat', 'No.', 'Student ID', 'STUDENT NAME', 'FYP TITLE',
        'SUPERVISOR', 'ASSESSOR', 'DOMAIN', 'PLATFORM ', 'TYPE',
    ];
}

test('Seat header maps to pair_number', function () {
    $map = app(CsvHeaderResolver::class)->resolve(fyp2OctHeaderRow());

    expect($map['pair_number'] ?? null)->toBe(0);
});

// The next three prove the missing CONTACT NO. column is harmless: resolution is
// by header name, so the shifted columns are still found at their new positions.

test('SUPERVISOR header still resolves when CONTACT NO. is absent', function () {
    $map = app(CsvHeaderResolver::class)->resolve(fyp2OctHeaderRow());

    expect($map['supervisor_name'] ?? null)->toBe(5);
});

test('PLATFORM header still resolves at its shifted position', function () {
    $map = app(CsvHeaderResolver::class)->resolve(fyp2OctHeaderRow());

    expect($map['application_type'] ?? null)->toBe(8);
});

test('TYPE header still resolves at its shifted position', function () {
    $map = app(CsvHeaderResolver::class)->resolve(fyp2OctHeaderRow());

    expect($map['is_ifyp'] ?? null)->toBe(9);
});
