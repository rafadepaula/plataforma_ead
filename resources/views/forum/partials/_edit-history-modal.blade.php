{{--
    SPEC-10 §2.1 — the "Editado em {edited_at}" badge + "ver histórico"
    modal. Visible to ANY user with access to the topic (not just the
    author or a Gestor/Admin) per §2.1's transparency requirement, so this
    partial applies no authorization check of its own — the parent
    `forum.show`/`forum.index` view only includes it when `$editedAt` is
    present.

    Migrado para Bootstrap 5.3 (Fase 2, Wave B / P7): o gatilho é
    declarativo (`data-bs-toggle="modal"` + `data-bs-target`), portanto o
    módulo `ForumEditHistory.js` (que delegava ao removido ModalManager e
    escondia `.dialog-backdrop` via `style.display`) deixou de existir.

    Expected variables (passed by the including view):
      - `$modalId`    unique DOM id for this post's history modal.
      - `$label`      "Tópico" or "Resposta" (used as the modal title).
      - `$editedAt`   \Carbon\Carbon|null — the post's current `edited_at`.
      - `$history`    Collection<\App\Models\ForumPostEdit> for this post,
                       ordered by `edited_at` desc (Bucket 2 contract —
                       `ForumPostEdit::query()->where('postable_type', ...)
                       ->where('postable_id', $post->id)
                       ->orderByDesc('edited_at')->get()`).
--}}
@if($editedAt)
    <span class="small text-body-secondary ms-2">
        Editado em {{ $editedAt->format('d/m/Y H:i') }}
        —
        <button
            type="button"
            class="btn btn-link p-0 align-baseline small text-decoration-underline"
            data-bs-toggle="modal"
            data-bs-target="#{{ $modalId }}"
            dusk="edit-history-trigger-{{ $modalId }}"
        >ver histórico</button>
    </span>

    <x-ui.modal id="{{ $modalId }}" title="Histórico de Edição — {{ $label }}" size="md">
        @forelse($history as $edit)
            <div class="py-2 border-bottom" dusk="edit-history-entry-{{ $edit->id }}">
                <div class="small text-body-secondary mb-1">
                    {{ optional($edit->editor)->name ?? 'Usuário removido' }} — {{ $edit->edited_at->format('d/m/Y H:i') }}
                </div>
                <div class="text-prewrap">{{ $edit->previous_content }}</div>
            </div>
        @empty
            <p class="text-body-secondary mb-0" dusk="edit-history-empty-{{ $modalId }}">
                Nenhuma versão anterior registrada.
            </p>
        @endforelse

        <x-slot:actions>
            <x-ui.button variant="ghost" data-bs-dismiss="modal">Fechar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
@endif
