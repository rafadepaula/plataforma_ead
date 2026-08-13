@extends('layouts.app')

@section('content')
    <x-slot:title>Configurações — Plataforma EAD</x-slot:title>

    <x-layout.page-header kicker="Sistema" title="Configurações" />

    {{-- `col-lg-6` substitui o antigo `max-width: 560px` do bloco de campos. --}}
    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" dusk="settings-form">
                    @csrf
                    @method('PUT')

                    <x-ui.field-stack>
                        <div class="kicker text-primary mb-2">SMTP</div>

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

                        <div class="kicker text-primary mt-2 mb-2">Identidade Visual</div>

                        {{-- `<x-ui.input type="file">` entrega label + `.form-control` +
                             `.is-invalid`/`.invalid-feedback`; o caminho do logo atual
                             viaja no `hint` (`.form-text`). --}}
                        <x-ui.input
                            type="file"
                            name="logo"
                            label="Logo"
                            accept="image/*"
                            :hint="! empty($settings['logo_path']) ? 'Logo atual: '.$settings['logo_path'] : null"
                        />

                        <x-ui.textarea
                            name="signature"
                            label="Assinatura de Certificados"
                            value="{{ $settings['signature'] ?? old('signature') }}"
                            help="Texto exibido como assinatura nos certificados emitidos."
                        />
                    </x-ui.field-stack>

                    <x-ui.form-actions>
                        <x-ui.button type="submit" dusk="settings-submit">Salvar Configurações</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
