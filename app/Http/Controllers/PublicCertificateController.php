<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Contracts\View\View;

/**
 * fully public, unauthenticated, cross-tenant
 * certificate validation (`GET /validar-certificado/{hash}`,
 * `certificates.verify`). `Certificate` carries no `OrgScope` of its own
 * and `Course` (cascade-inherited `OrgScope`) is deliberately read
 * `withoutGlobalScopes()` here, so a hash issued by ANY Organization
 * resolves regardless of the visitor's (nonexistent) tenant context.
 *
 * A hash that never existed 404s via `firstOrFail()`. A hash that DOES
 * exist but is revoked still resolves and renders — see §2 — the
 * "valid vs revoked" split is handled entirely inside the Blade view via
 * `Certificate::isRevoked()`, never here.
 */
class PublicCertificateController extends Controller
{
    public function show(string $hash): View
    {
        $certificate = Certificate::query()
            ->where('validation_hash', $hash)
            ->with(['user'])
            ->firstOrFail();

        $certificate->setRelation(
            'course',
            $certificate->course()->withoutGlobalScopes()->with('organization')->firstOrFail(),
        );

        return view('public.certificates.show', ['certificate' => $certificate]);
    }
}
