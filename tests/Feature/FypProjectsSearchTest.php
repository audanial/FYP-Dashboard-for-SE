<?php

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('fyp projects search is case-insensitive', function () {
    $coordinator = User::factory()->create(['role' => 'coordinator']);

    FypProject::factory()->create([
        'student_name' => 'Alice Tan',
        'student_id'   => '5221312099',
        'title'        => 'Smart Campus System',
        'fyp_phase'    => 'FYP 1',
        'semester'     => 'MARCH 2026',
    ]);

    $this->actingAs($coordinator);

    Livewire::test('fyp-projects')
        ->set('search', 'alice')
        ->assertSee('Alice Tan');
});
