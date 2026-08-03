{{--
    SPEC-10 §2.1 — the "Editado em {edited_at}" badge + "ver histórico"
    modal. Visible to ANY user with access to the topic (not just the
    author or a Gestor/Admin) per §2.1's transparency requirement, so this
    partial applies no authorization check of its own — the parent
    `forum.show`/`forum.index` view only includes it when `$editedAt` is
    present.

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
    <span style="font-size: 11px; color: var(--color-neutral-600); margin-left: 8px;">
        Editado em {{ $editedAt->format('d/m/Y H:i') }}
        —
        <button
            type="button"
            class="btn btn-ghost"
            style="display: inline; padding: 0; border: 0; background: none; font-size: 11px; text-decoration: underline; color: var(--color-accent); cursor: pointer;"
            data-modal-target="{{ $modalId }}"
            data-edit-history-trigger
            dusk="edit-history-trigger-{{ $modalId }}"
        >ver histórico</button>
    </span>

    <x-ui.modal id="{{ $modalId }}" title="Histórico de Edição — {{ $label }}" size="md">
        @forelse($history as $edit)
            <div style="padding: 10px 0; border-bottom: 1px solid var(--color-divider);" dusk="edit-history-entry-{{ $edit->id }}">
                <div style="font-size: 11px; color: var(--color-neutral-600); margin-bottom: 4px;">
                    {{ optional($edit->editor)->name ?? 'Usuário removido' }} — {{ $edit->edited_at->format('d/m/Y H:i') }}
                </div>
                <div style="font-size: 13px; white-space: pre-wrap;">{{ $edit->previous_content }}</div>
            </div>
        @empty
            <p style="color: var(--color-neutral-600); font-size: 13px;" dusk="edit-history-empty-{{ $modalId }}">
                Nenhuma versão anterior registrada.
            </p>
        @endforelse

        <x-slot:actions>
            <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Fechar</button>
        </x-slot:actions>
    </x-ui.modal>
@endif
