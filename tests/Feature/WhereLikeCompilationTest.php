<?php

use App\Models\FypProject;
use App\Models\User;

/*
 * Laravel's built-in whereLike()/orWhereLike() (caseSensitive defaults to
 * false) already pick ILIKE on pgsql and LIKE elsewhere per the query
 * grammar, so search stays case-insensitive across drivers without any
 * custom code. This locks in that our own call sites compile the way we
 * expect on the SQLite test connection (phpunit.xml) — "like", never
 * "ilike". No real pgsql connection is available in this environment to
 * assert the ilike branch directly; that's covered by Laravel's own tests.
 */

test('whereLike compiles to like on the sqlite test connection', function () {
    $sql = FypProject::query()->whereLike('student_name', 'x')->toSql();

    expect($sql)->toContain('like')
        ->and($sql)->not->toContain('ilike');
});

test('orWhereLike compiles two like clauses joined by or', function () {
    $sql = User::query()
        ->whereLike('name', 'x')
        ->orWhereLike('email', 'x')
        ->toSql();

    expect(substr_count($sql, 'like'))->toBe(2)
        ->and($sql)->toContain('or');
});
