<?php

namespace App\Events;

use App\Models\InvitationLink;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SPEC-13 §2 gatilho 1 — dispatched by `InvitationLinkController::store()`
 * right after a new `InvitationLink` row is created.
 * `SendInvitationSentNotification` is the sole listener, auto-discovered
 * from its `handle()` type-hint.
 */
class InvitationLinkCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public InvitationLink $invitationLink) {}
}
