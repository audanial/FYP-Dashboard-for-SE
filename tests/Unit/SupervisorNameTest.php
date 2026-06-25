<?php

use App\Support\SupervisorName;

/*
 * Item 6: supervisor names are matched to the roster by a normalized slug.
 * The CSV SUPERVISOR column is messy ("Rohaya Abu Hassan - Ts.",
 * "Tiliza binti Awang Mat"); the slug strips titles and relationship
 * particles so it collapses onto the roster's canonical name_slug.
 */

test('a clean name slugs to lowercase underscore form', function () {
    expect(SupervisorName::slug('Azizah Rahmat'))->toBe('azizah_rahmat');
});

test('a leading Dr. title is stripped', function () {
    expect(SupervisorName::slug('Dr. Azaliza Zainal'))->toBe('azaliza_zainal');
});

test('a trailing dash + Ts. suffix is stripped', function () {
    expect(SupervisorName::slug('Rohaya Abu Hassan - Ts.'))->toBe('rohaya_abu_hassan');
});

test('the Assoc. Prof. title is stripped', function () {
    expect(SupervisorName::slug('Assoc. Prof. Chen Xinyuan'))->toBe('chen_xinyuan');
});

test('the binti particle is stripped', function () {
    expect(SupervisorName::slug('Tiliza binti Awang Mat'))->toBe('tiliza_awang_mat');
});

test('the bin particle is stripped', function () {
    expect(SupervisorName::slug('Mohammad bin Faizuddin'))->toBe('mohammad_faizuddin');
});

test('the a/l and a/p particles are stripped', function () {
    expect(SupervisorName::slug('Suguneswari a/p Raja Gopal'))->toBe('suguneswari_raja_gopal')
        ->and(SupervisorName::slug('Vinod a/l Kumar'))->toBe('vinod_kumar');
});

test('newlines and repeated whitespace collapse to single underscores', function () {
    expect(SupervisorName::slug("Noor\n Widasuria  Abu Bakar"))->toBe('noor_widasuria_abu_bakar');
});

test('a messy CSV name and its clean roster name produce the same slug', function () {
    $rosterSlug = SupervisorName::slug('Tiliza Awang Mat');
    $csvSlug    = SupervisorName::slug('Tiliza binti Awang Mat - Ts.');

    expect($csvSlug)->toBe($rosterSlug);
});
