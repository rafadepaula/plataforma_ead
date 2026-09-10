<?php

namespace App\Services;

use App\Models\Credential;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * A {@see UserProvider} whose passwords, statuses and remember tokens live
 * in the `credentials` table — one account per (user, organization) pair,
 * where the organization is the one resolved from the request host by
 * `ResolveOrgFromHost`. Lookup by e-mail stays global (people are unique);
 * validation is per-Organization:
 *
 * - A person with an account only in Organization A fails to log in on
 *   Organization B's portal exactly like a wrong password would — the
 *   generic `auth.failed` message is the ONLY outcome the client sees.
 * - The global Admin account (`credentials.org_id = null`) validates on
 *   every host, including "state zero" (unmapped host), where it is the
 *   only account that can pass.
 * - An inactive Organization rejects every login regardless of the
 *   credential being valid — its landing stays viewable, its login does
 *   not.
 */
final class OrgCredentialUserProvider extends EloquentUserProvider
{
    /**
     * Look up the person by e-mail only. Account-level keys (`password`,
     * `status`) are deliberately NOT translated into `users` WHERE clauses —
     * those columns live in `credentials` and are validated in
     * {@see self::validateCredentials()} against the host's Organization.
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?AuthenticatableContract
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        /** @var AuthenticatableContract|null $user */
        $user = $this->newModelQuery()->where('email', $email)->first();

        return $user;
    }

    public function validateCredentials(AuthenticatableContract $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        $context = OrgContext::current();

        // An inactive tenant serves its landing page, but never a login.
        if ($context->organization !== null && ! $context->orgIsActive) {
            return false;
        }

        $credential = Credential::query()
            ->forOrg($context->orgId())
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($credential === null || $credential->status !== 'active') {
            return false;
        }

        if (! $this->hasher->check($password, $credential->password)) {
            return false;
        }

        $this->rehashCredentialIfRequired($credential, $password);

        return true;
    }

    /**
     * "Remember me" cookie recall, per (user, host-org) account: the token
     * is only valid on the Organization whose account issued it.
     */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?AuthenticatableContract
    {
        $context = OrgContext::current();

        if ($context->organization !== null && ! $context->orgIsActive) {
            return null;
        }

        $user = $this->newModelQuery()->find($identifier);

        if ($user === null) {
            return null;
        }

        $hasValidToken = Credential::query()
            ->forOrg($context->orgId())
            ->where('user_id', $identifier)
            ->where('remember_token', $token)
            ->exists();

        return $hasValidToken ? $user : null;
    }

    /**
     * Persists the new remember token on the (user, host-org) account —
     * the base implementation writes `users.remember_token`, a column that
     * no longer exists.
     */
    public function updateRememberToken(AuthenticatableContract $user, #[\SensitiveParameter] $token): void
    {
        Credential::query()
            ->forOrg(OrgContext::current()->orgId())
            ->where('user_id', $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    public function rehashPasswordIfRequired(AuthenticatableContract $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return;
        }

        $credential = Credential::query()
            ->forOrg(OrgContext::current()->orgId())
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($credential !== null) {
            $this->rehashCredentialIfRequired($credential, $password, $force);
        }
    }

    private function rehashCredentialIfRequired(Credential $credential, string $password, bool $force = false): void
    {
        if ($force || $this->hasher->needsRehash($credential->password)) {
            $credential->forceFill(['password' => $this->hasher->make($password)])->save();
        }
    }
}
