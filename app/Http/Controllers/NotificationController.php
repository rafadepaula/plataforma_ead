<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX endpoints backing the topbar notification bell.
 * `DatabaseNotification` carries no `OrgScope`/Policy of its own (see
 * 's guardrails), so every query here is manually scoped to
 * `$request->user()->notifications()` (the `Notifiable` trait's
 * `MorphMany`) rather than a route-model-bound `{notification}` — this is
 * what guarantees  (no cross-user leak) without a dedicated Policy.
 */
class NotificationController extends Controller
{
    /**
     * `GET /notifications/unread-count` — polled every 30s by
     * `NotificationBell.js` to keep the topbar badge in sync.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * `PATCH /notifications/read-all` — marks every unread notification of
     * the authenticated user as read (the dropdown's "marcar todas como
     * lidas" link).
     */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * `PATCH /notifications/{notification}/read` — marks a single
     * notification as read and hands back its `action_url` for the
     * client-side redirect. `$notification` is a plain string (UUID), not
     * a typed model binding — `DatabaseNotification` has no Policy, so it
     * is resolved from `$request->user()->notifications()` instead,
     * 404-ing for any id not owned by the authenticated user (including a
     * genuinely nonexistent id, indistinguishable on purpose).
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $notificationModel = $request->user()->notifications()->findOrFail($notification);

        $notificationModel->markAsRead();

        return response()->json([
            'action_url' => $notificationModel->data['action_url'] ?? null,
        ]);
    }
}
