<?php

// TEMPORARY: replicates local demo accounts on production (no UI path exists
// for this). Delete this file and the entrypoint.sh line that requires it
// once the next deploy confirms both accounts work.

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;

// 1. Reset tiliza's password to a known value.
User::where('email', 'tiliza@unikl.edu.my')->update(['password' => bcrypt('abcd1234')]);

// 2. Recreate the demo student account. username = student_id links it (via
//    FypProject.student_id) to the existing ABDUL AZZINUDDIN BIN ABDUL LATIFF
//    fyp_projects rows, which already have supervisor_id = tiliza's user id.
$student = User::updateOrCreate(
    ['email' => 'student@s.unikl.edu.my'],
    [
        'name' => 'Test Student',
        'username' => '52213120604',
        'role' => 'student',
        'password' => bcrypt('abcd1234'),
        'is_active' => true,
    ],
);

// 3. One logbook entry, keyed on (user_id, title) so re-running this is safe.
Logbook::updateOrCreate(
    ['user_id' => $student->id, 'title' => 'Week 9 progress update'],
    [
        'content' => "- Fix some UI/UX layout that affects the placement of the wording.\n- Fix some database issues.\n",
        'date' => '2026-06-23',
    ],
);
