<?php

namespace App\Actions\Jetstream;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\CreatesTeams;

class CreateTeam implements CreatesTeams
{
    public function create($user, array $input)
    {
        // Business rule: user cannot create more than ONE team.
        // With Jetstream Teams enabled, the personal team already exists → block creating additional teams.
        throw ValidationException::withMessages([
            'team' => 'Team creation is disabled. Use your existing workspace team.',
        ]);
    }
}