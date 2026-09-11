<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * provisions a person's account (credential) in one Organization from
 * the staff CRUDs. The e-mail is the GLOBAL person identity: when it
 * already belongs to someone — of another portal — that same `User` row
 * is reused and a NEW credential is created here with the form's
 * password; the person's other-organization accounts are never touched.
 * A person who already holds an account in THIS organization is a form
 * error, never a silent password rewrite.
 */
final class ProvisionOrgAccountAction
{
    /**
     * @param  string  $role  Spatie role slug (roles are global — phase 1)
     *
     * @throws ValidationException
     */
    public function execute(
        Organization $organization,
        string $name,
        string $email,
        ?string $cpf,
        #[\SensitiveParameter] string $password,
        string $role,
    ): User {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::create(['name' => $name, 'email' => $email, 'cpf' => $cpf]);
        } elseif ($user->credentialFor($organization) !== null) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail já possui conta nesta organização.'],
            ]);
        }

        // additive on purpose: reuse never demotes the person elsewhere
        $user->assignRole($role);

        $user->credentials()->create([
            'org_id' => $organization->id,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        return $user;
    }
}
