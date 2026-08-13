@php
    /** @var \App\Models\Organization $organization */
@endphp

<div class="max-w-560">
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

    <div class="mb-3">
        <label for="logo" class="form-label">Logo</label>
        <input type="file" id="logo" name="logo" accept="image/*"
               class="form-control @error('logo') is-invalid @enderror" />
        @if($organization->logo_path)
            <div class="form-text">Logo atual: {{ $organization->logo_path }}</div>
        @endif
        @error('logo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
