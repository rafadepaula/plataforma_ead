<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\UpdateSystemSettingRequest;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `GET`/`PUT /admin/settings` (route names `settings.edit`/
 * `settings.update`, see `dashboard-conventions`) — a
 * system-administration surface reserved to `role:admin` (the Gestor
 * lost the menu item AND the reachability). Reads/writes the
 * org-override SMTP/logo/signature settings via `SettingService`, which
 * already resolves the org-specific-then-global fallback — this
 * controller only resolves *which* org is acting (mirrors
 * `DashboardController`/`ReportExportController`'s resolution, never a
 * request-supplied `org_id`) and always writes to that org's row (or the
 * global row for an Admin with no active Impersonate Org). The non-Admin
 * branch of `resolveViewingOrgId()` stays as defense in depth, though no
 * route can reach it with a Gestor anymore.
 */
class SystemSettingController extends Controller
{
    /**
     * @var list<string>
     */
    private const TEXT_KEYS = ['smtp_host', 'smtp_port', 'smtp_username', 'signature'];

    public function __construct(protected SettingService $settingService) {}

    public function edit(Request $request): View
    {
        $orgId = $this->resolveViewingOrgId($request);

        $settings = [];

        foreach (self::TEXT_KEYS as $key) {
            $settings[$key] = $this->settingService->get($key, $orgId);
        }

        $settings['logo_path'] = $this->settingService->get('logo_path', $orgId);

        return view('settings.edit', ['settings' => $settings]);
    }

    public function update(UpdateSystemSettingRequest $request): RedirectResponse
    {
        $orgId = $this->resolveViewingOrgId($request);
        $data = $request->validated();

        foreach (self::TEXT_KEYS as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $this->settingService->set($key, $orgId, $data[$key]);
            }
        }

        if (! empty($data['smtp_password'])) {
            $this->settingService->set('smtp_password', $orgId, $data['smtp_password']);
        }

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('settings/logos', 'public');
            $this->settingService->set('logo_path', $orgId, $logoPath);
        }

        return redirect()->route('settings.edit')->with('status', 'Configurações salvas com sucesso.');
    }

    /**
     * Mirrors `DashboardController::resolveViewingOrgId()` — never
     * trusts a request-supplied `org_id`.
     */
    protected function resolveViewingOrgId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            $activeOrgId = session('active_org_id');

            return $activeOrgId ? (int) $activeOrgId : null;
        }

        return $user->org_id ? (int) $user->org_id : null;
    }
}
