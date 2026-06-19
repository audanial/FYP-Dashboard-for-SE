<?php

namespace App\Console\Commands;

use App\Models\FypProject;
use App\Models\User;
use Illuminate\Console\Command;

class LinkSupervisors extends Command
{
    protected $signature = 'fyp:link-supervisors';

    protected $description = 'Backfill fyp_projects.supervisor_id by exact match of supervisor_name to a supervisor account. Idempotent — already-linked rows are skipped.';

    public function handle(): int
    {
        // Build name→id map for supervisor accounts only. Non-supervisor accounts
        // with a matching name are intentionally excluded.
        $idByName = User::where('role', 'supervisor')->pluck('id', 'name');

        $linked    = 0;
        $unmatched = [];

        $names = FypProject::whereNull('supervisor_id')
            ->whereNotNull('supervisor_name')
            ->where('supervisor_name', '!=', '')
            ->distinct()
            ->pluck('supervisor_name');

        foreach ($names as $name) {
            if (isset($idByName[$name])) {
                $linked += FypProject::where('supervisor_name', $name)
                    ->whereNull('supervisor_id')
                    ->update(['supervisor_id' => $idByName[$name]]);
            } else {
                $unmatched[] = $name;
            }
        }

        $remaining = FypProject::whereNull('supervisor_id')->count();

        $this->info("Linked {$linked} row(s) by exact match.");
        $this->info('Unmatched names: ' . count($unmatched));
        foreach ($unmatched as $name) {
            $this->line("  • {$name}");
        }
        $this->info("Remaining unlinked rows: {$remaining}");

        return self::SUCCESS;
    }
}
