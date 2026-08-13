{{--
    SPEC-13 §4 (RF28) — the topbar notification bell. Only visible to
    `role:gestor`/`role:aluno` (Admin doesn't receive Org-specific business
    notifications, see spec §4). Renders the badge (server-side initial
    unread count, kept fresh client-side by `NotificationBell.js`'s 30s
    polling of `notifications.unread-count`) and a dropdown with the 10
    most recent `notifications` rows (`ORDER BY created_at DESC`), a
    ---
    Abrir/fechar é 100% declarativo (`data-bs-toggle="dropdown"` +
    `.dropdown-menu`): posicionamento (Popper), clique-fora, Escape e ARIA
    são do `bootstrap.Dropdown`. `NotificationBell.js` não toca nisso.
    ---
    "marcar todas como lidas" link (`notifications.read-all`), and one
    clickable item per notification that marks itself read
    (`notifications.read`) before redirecting to `data.action_url`.
--}}
@php
    use App\Enums\Permissions\RolesEnum;

    $notifUser = auth()->user();
    $canSeeNotifications = $notifUser
        && ($notifUser->hasRole(RolesEnum::GESTOR->value) || $notifUser->hasRole(RolesEnum::ALUNO->value));

    $recentNotifications = collect();
    $unreadCount = 0;

    if ($canSeeNotifications) {
        $recentNotifications = $notifUser->notifications()->latest('created_at')->limit(10)->get();
        $unreadCount = $notifUser->unreadNotifications()->count();
    }
@endphp

@if($canSeeNotifications)
    <div
        class="dropdown"
        data-notifications-bell
        data-unread-count-url="{{ route('notifications.unread-count') }}"
        data-mark-all-read-url="{{ route('notifications.read-all') }}"
        dusk="notifications-bell"
    >
        <button
            type="button"
            class="btn btn-link text-body text-decoration-none p-0 position-relative"
            aria-label="Notificações"
            data-notifications-toggle
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            data-bs-boundary="viewport"
            aria-expanded="false"
            dusk="notifications-toggle"
        >
            <x-ui.icon name="bell" size="18" />
            <span
                data-notifications-badge
                dusk="notifications-badge"
                class="position-absolute top-0 start-100 translate-middle-x {{ $unreadCount > 0 ? 'd-flex' : 'd-none' }} align-items-center justify-content-center bg-danger text-white fw-bold min-w-16 h-16 px-1 fs-10 lh-1"
            >{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        </button>

        <div
            class="dropdown-menu dropdown-menu-end shadow-lg w-340 max-w-90vw max-h-420"
            data-notifications-dropdown
            dusk="notifications-dropdown"
        >
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                <span class="fw-bold fs-6 text-body">Notificações</span>
                <a
                    href="#"
                    class="text-primary text-decoration-none small"
                    data-notifications-mark-all
                    dusk="notifications-mark-all-read"
                >marcar todas como lidas</a>
            </div>

            <div class="overflow-y-auto max-h-360" data-notifications-list>
                @forelse($recentNotifications as $notification)
                    <a
                        href="{{ $notification->data['action_url'] ?? '#' }}"
                        class="dropdown-item text-wrap p-3 border-bottom {{ $notification->read_at ? '' : 'bg-primary bg-opacity-10 fw-semibold' }}"
                        data-notifications-item
                        data-notification-id="{{ $notification->id }}"
                        data-mark-read-url="{{ route('notifications.read', $notification->id) }}"
                        dusk="notifications-item-{{ $notification->id }}"
                    >
                        <div>{{ $notification->data['message'] ?? 'Nova notificação' }}</div>
                        <div class="small text-body-secondary mt-1">
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </a>
                @empty
                    <div
                        class="p-4 text-center text-body-secondary small"
                        dusk="notifications-empty"
                    >
                        Nenhuma notificação por aqui.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif
