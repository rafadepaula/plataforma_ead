{{--
    SPEC-13 §4 (RF28) — the topbar notification bell. Only visible to
    `role:gestor`/`role:aluno` (Admin doesn't receive Org-specific business
    notifications, see spec §4). Renders the badge (server-side initial
    unread count, kept fresh client-side by `NotificationBell.js`'s 30s
    polling of `notifications.unread-count`) and a dropdown with the 10
    most recent `notifications` rows (`ORDER BY created_at DESC`), a
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
        data-notifications-bell
        data-unread-count-url="{{ route('notifications.unread-count') }}"
        data-mark-all-read-url="{{ route('notifications.read-all') }}"
        dusk="notifications-bell"
        style="position: relative;"
    >
        <button
            type="button"
            class="btn btn-ghost btn-icon"
            aria-label="Notificações"
            data-notifications-toggle
            dusk="notifications-toggle"
            style="color: var(--color-text); position: relative; border-radius: 0px;"
        >
            <x-ui.icon name="bell" size="18" />
            <span
                data-notifications-badge
                dusk="notifications-badge"
                style="display: {{ $unreadCount > 0 ? 'flex' : 'none' }}; position: absolute; top: -2px; right: -2px; min-width: 16px; height: 16px; padding: 0 3px; background: var(--color-accent-2); color: var(--color-neutral-100); font-size: 10px; font-weight: 700; line-height: 1; align-items: center; justify-content: center; border-radius: 0px;"
            >{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        </button>

        <div
            data-notifications-dropdown
            dusk="notifications-dropdown"
            style="display: none; position: absolute; right: 0; top: calc(100% + 8px); width: 340px; max-width: 90vw; max-height: 420px; overflow-y: auto; background: var(--color-surface); border: 1px solid var(--color-divider); box-shadow: var(--shadow-lg); z-index: 90; border-radius: 0px;"
        >
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--color-divider);">
                <span style="font-weight: 700; font-size: 13px; color: var(--color-text);">Notificações</span>
                <a
                    href="#"
                    data-notifications-mark-all
                    dusk="notifications-mark-all-read"
                    style="font-size: 12px; color: var(--color-accent); text-decoration: none;"
                >marcar todas como lidas</a>
            </div>

            <div data-notifications-list>
                @forelse($recentNotifications as $notification)
                    <a
                        href="{{ $notification->data['action_url'] ?? '#' }}"
                        data-notifications-item
                        data-notification-id="{{ $notification->id }}"
                        data-mark-read-url="{{ route('notifications.read', $notification->id) }}"
                        dusk="notifications-item-{{ $notification->id }}"
                        style="display: block; padding: 12px 16px; border-bottom: 1px solid var(--color-divider); text-decoration: none; color: var(--color-text); font-size: 13px; {{ $notification->read_at ? '' : 'background: color-mix(in srgb, var(--color-accent) 6%, transparent); font-weight: 600;' }}"
                    >
                        <div>{{ $notification->data['message'] ?? 'Nova notificação' }}</div>
                        <div style="font-size: 11px; color: var(--color-neutral-600); margin-top: 4px;">
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </a>
                @empty
                    <div
                        dusk="notifications-empty"
                        style="padding: 24px 16px; text-align: center; font-size: 13px; color: var(--color-neutral-600);"
                    >
                        Nenhuma notificação por aqui.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif
