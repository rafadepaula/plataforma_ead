{{--
    SPEC-15 §5 — the "Ver diff" modal shared by every row in
    `audit-logs/index.blade.php`. Unlike `forum/partials/_edit-history-modal.blade.php`
    (one modal per post), a single shared modal is used here — each
    "Ver diff" button inlines its own row's `old_values`/`new_values` as
    JSON in `data-old-values`/`data-new-values` attributes (avoids one
    `<x-ui.modal>` per row across a 25-row page), and
    `resources/js/modules/AuditLogDiffModal.js` fills this modal's body
    from the clicked button's dataset before delegating the actual
    open to `window.ModalManager` (same `data-modal-target` contract as
    every other modal in this codebase).
--}}
<x-ui.modal id="audit-diff-modal" title="Diff do Registro de Auditoria" size="lg">
    <div style="margin-bottom: 12px;">
        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--color-neutral-600);">Evento:</span>
        <span dusk="audit-diff-event"></span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <h4 style="font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 0 0 8px;">Valores Anteriores</h4>
            <pre dusk="audit-diff-old" style="background: color-mix(in srgb, var(--color-neutral-900) 5%, transparent); padding: 12px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-word;"></pre>
        </div>
        <div>
            <h4 style="font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 0 0 8px;">Novos Valores</h4>
            <pre dusk="audit-diff-new" style="background: color-mix(in srgb, var(--color-neutral-900) 5%, transparent); padding: 12px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-word;"></pre>
        </div>
    </div>

    <x-slot:actions>
        <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Fechar</button>
    </x-slot:actions>
</x-ui.modal>
