<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
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
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Todo usuario que se registra recibe el rol Cliente (rol de negocio publico),
        // con vigencia desde hoy y sin fecha de fin. Asi obtiene su menu al instante.
        $cliente = Rol::where('nombre', 'Cliente')->where('activo', true)->first();
        if ($cliente) {
            $user->roles()->syncWithoutDetaching([
                $cliente->id => ['fini' => now()->toDateString(), 'ffin' => null, 'activo' => true],
            ]);
        }

        return $user;
    }
}
