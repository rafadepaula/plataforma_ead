@extends('layouts.app')

@section('content')
    <x-slot:title>Configurações — Plataforma EAD</x-slot:title>

    <x-ui.card title="Configurações" kicker="Sistema">
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" dusk="settings-form">
            @csrf
            @method('PUT')

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 560px;">
                <div style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-accent); font-weight: 700;">
                    SMTP
                </div>

                <x-ui.input
                    name="smtp_host"
                    label="Host SMTP"
                    value="{{ $settings['smtp_host'] ?? old('smtp_host') }}"
                />

                <x-ui.input
                    name="smtp_port"
                    label="Porta SMTP"
                    type="number"
                    value="{{ $settings['smtp_port'] ?? old('smtp_port') }}"
                />

                <x-ui.input
                    name="smtp_username"
                    label="Usuário SMTP"
                    value="{{ $settings['smtp_username'] ?? old('smtp_username') }}"
                />

                <x-ui.input
                    name="smtp_password"
                    label="Senha SMTP"
                    type="password"
                    hint="Deixe em branco para manter a senha atual."
                />

                <div style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-accent); font-weight: 700; margin-top: 8px;">
                    Identidade Visual
                </div>

                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="logo" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Logo</label>
                    <input type="file" id="logo" name="logo" accept="image/*"
                           style="border-radius: 0px; border: 1px solid var(--color-divider); padding: 8px 12px; background: var(--color-surface); color: var(--color-text);" />
                    @if(!empty($settings['logo_path']))
                        <span style="font-size: 12px; color: var(--color-neutral-600);">Logo atual: {{ $settings['logo_path'] }}</span>
                    @endif
                    @error('logo')
                        <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $message }}</span>
                    @enderror
                </div>

                <x-ui.input
                    name="signature"
                    label="Assinatura de Certificados"
                    type="textarea"
                    value="{{ $settings['signature'] ?? old('signature') }}"
                    hint="Texto exibido como assinatura nos certificados emitidos."
                />
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="settings-submit">Salvar Configurações</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
