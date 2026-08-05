@php
    /** @var \App\Models\Organization $organization */
@endphp

<div style="display: flex; flex-direction: column; gap: 20px; max-width: 560px;">
    <x-ui.input
        name="name"
        label="Nome"
        required
        value="{{ $organization->name }}"
    />

    <x-ui.input
        name="slug"
        label="Slug"
        value="{{ $organization->slug }}"
        hint="Deixe em branco para gerar automaticamente a partir do Nome."
    />

    <x-ui.input
        name="cnpj"
        label="CNPJ"
        value="{{ $organization->cnpj }}"
        placeholder="00.000.000/0000-00"
        hint="Opcional. Usado apenas para identificação em certificados."
    />

    <x-ui.select
        name="status"
        label="Status"
        required
        :options="['active' => 'Ativo', 'inactive' => 'Inativo']"
        :selected="$organization->status ?? 'active'"
    />

    <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
        <label for="logo" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Logo</label>
        <input type="file" id="logo" name="logo" accept="image/*"
               style="border-radius: 0px; border: 1px solid var(--color-divider); padding: 8px 12px; background: var(--color-surface); color: var(--color-text);" />
        @if($organization->logo_path)
            <span style="font-size: 12px; color: var(--color-neutral-600);">Logo atual: {{ $organization->logo_path }}</span>
        @endif
        @error('logo')
            <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $message }}</span>
        @enderror
    </div>
</div>
