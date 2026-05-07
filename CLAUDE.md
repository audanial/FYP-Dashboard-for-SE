# FYP Dashboard System — UniKL MIIT

## What this is
A Laravel-based Final Year Project (FYP) tracking dashboard for Software Engineering students at UniKL MIIT. Built for FYP coordinators, supervisors, and students.

## Tech Stack
- Laravel 11 + Livewire Volt + Flux UI + Tailwind CSS
- SQLite database
- Pest for feature/unit tests
- Playwright for E2E tests

## Roles
- **coordinator** — manages users, imports CSV, views all projects
- **supervisor** — views their assigned students and student logbooks
- **student** — manages their own logbook entries

## Current Priority
Stabilizing and securing the system before adding new features. Fix security issues first, then improve test coverage.

## Key Files
- `resources/views/livewire/` — all Livewire Volt components
- `app/Models/` — FypProject, User, Logbook
- `app/Http/Middleware/EnsureUserHasRole.php` — role middleware
- `app/Services/CsvHeaderResolver.php` — CSV header mapping service
- `tests/Feature/` — Pest tests

## Rules
- Always run `./vendor/bin/pest` after making changes
- Never remove existing tests
- All Livewire actions must have server-side authorization checks, not just UI-level hiding
- Validate all user inputs on the server side
