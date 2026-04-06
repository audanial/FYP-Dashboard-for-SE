<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'staff_access_code' => ['nullable', 'string'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $this->generateUsername($input['email']),
            'password' => $input['password'],
            'role' => $this->resolveRole($input['staff_access_code'] ?? null),
        ]);
    }

    protected function resolveRole(?string $staffAccessCode): string
    {
        return match (Str::upper(trim((string) $staffAccessCode))) {
            'SE-PC-2026' => 'admin',
            'SE-SV-2026' => 'supervisor',
            default => 'student',
        };
    }

    protected function generateUsername(string $email): string
    {
        $baseUsername = Str::slug(Str::before($email, '@'), separator: '_');

        if ($baseUsername === '') {
            $baseUsername = 'user';
        }

        $username = $baseUsername;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = $baseUsername.'_'.$suffix;
            $suffix++;
        }

        return $username;
    }
}
