{{--
    SPEC-15 §5 — the "Ver diff" modal shared by every row in
    `audit-logs/index.blade.php`. Unlike `forum/partials/_edit-history-modal.blade.php`
    (one modal per post), a single shared modal is used here — each
    "Ver diff" button inlines its own `old_values`/`new_values` as
    JSON in `data-old-values`/`data-new-values` attributes (avoids one
    `<x-ui.modal>` per row across a 25-row page), and
    `resources/js/modules/AuditLogDiffModal.js` fills this modal's body
    from the clicked button's dataset before opening it through
    `bootstrap.Modal.getOrCreateInstance()`.
--}}
<x-ui.modal id="audit-diff-modal" title="Diff do Registro de Auditoria" size="lg">
    <div class="mb-3">
        <span class="small fw-bold text-uppercase text-body-secondary">Evento:</span>
        <span dusk="audit-diff-event"></span>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <h4 class="small fw-bolder text-uppercase mb-2">Valores Anteriores</h4>
            <pre dusk="audit-diff-old"
                 class="bg-white border p-3 mb-0 small font-monospace text-prewrap text-break overflow-auto"></pre>
        </div>
        <div class="col-md-6">
            <h4 class="small fw-bolder text-uppercase mb-2">Novos Valores</h4>
            <pre dusk="audit-diff-new"
                 class="bg-white border p-3 mb-0 small font-monospace text-prewrap text-break overflow-auto"></pre>
        </div>
    </div>

    <x-slot:actions>
        <x-ui.button variant="ghost" data-bs-dismiss="modal">Fechar</x-ui.button>
    </x-slot:actions>
</x-ui.modal>
