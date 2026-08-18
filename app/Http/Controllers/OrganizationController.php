<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\Navigation\ImpersonationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Organization CRUD, reserved to `role:admin` (see
 * `routes/web.php` and `OrganizationPolicy`).
 */
class OrganizationController extends Controller
{
    public function index(ImpersonationContext $impersonation): View
    {
        Gate::authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->orderBy('name')
            ->paginate(15);

        return view('organizations.index', [
            'organizations' => $organizations,
            // UX-002 — the point-of-origin banner reads the same resolved
            // Organization as the topbar badge instead of running its own
            // `Organization::find()` inside the Blade.
            'activeOrganization' => $impersonation->activeOrganization(auth()->user()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Organization::class);

        return view('organizations.create', ['organization' => new Organization]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('organizations/logos', 'public');
        }
        unset($data['logo']);

        Organization::create($data);

        return redirect()->route('organizations.index')
            ->with('success', 'Organização criada com sucesso.');
    }

    public function edit(Organization $organization): View
    {
        Gate::authorize('update', $organization);

        return view('organizations.edit', ['organization' => $organization]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('organizations/logos', 'public');
        }
        unset($data['logo']);

        $organization->update($data);

        return redirect()->route('organizations.index')
            ->with('success', 'Organização atualizada com sucesso.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        // `users.org_id` is `ON DELETE RESTRICT`, so a
        // hard delete with existing users would fail at the DB level.
        // Only ever soft-delete here.
        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', 'Organização removida com sucesso.');
    }

    /**
     * Derive a unique slug from `name` when the caller didn't provide one
     * explicitly, appending a numeric suffix on collision.
     */
    private function resolveSlug(string $name, ?string $slug): string
    {
        if ($slug) {
            return $slug;
        }

        $base = Str::slug($name);
        $candidate = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
