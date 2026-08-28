@props([
    'certificate' => null,
    'progressPercentage' => null,
])

@php
    /** Logical revocation keeps the record resolvable, but it must never be downloadable. */
    $isRevoked = $certificate !== null && $certificate->isRevoked();
    $isAvailable = $certificate !== null && ! $isRevoked;
    /** The stored validation hash is 64 chars; show a readable prefix inside the 4-col card. */
    $readableCode = $certificate !== null
        ? strtoupper(substr((string) $certificate->validation_hash, 0, 12))
        : null;
@endphp

<x-ui.card title="Certificado" {{ $attributes }}>
    @if($isAvailable)
        <div class="ds-cert-issued">
            <span class="ds-cert-icon">
                <x-ui.icon name="award" :size="22" aria-hidden="true" />
            </span>
            <div>
                <div class="fw-semibold">
                    @if($certificate->issued_at)
                        Emitido em {{ $certificate->issued_at->format('d/m/Y') }}
                    @else
                        Emitido
                    @endif
                </div>
                <div class="ds-caption text-secondary">Certificado nº</div>
                <div class="ds-cert-code text-break">{{ $readableCode }}</div>
            </div>
        </div>

        <x-ui.button variant="primary"
                     :href="route('certificates.download', $certificate)"
                     dusk="download-certificate">
            Baixar certificado
        </x-ui.button>

        {{--
            O código exibido é um prefixo legível do hash de 64 caracteres; o
            verificador público exige o hash completo, então a conferência sai
            daqui por link e nunca por digitação manual. Sem `dusk=`: o
            snapshot de seletores é um conjunto fechado.
        --}}
        <a class="ds-caption d-inline-block mt-2"
           href="{{ route('certificates.verify', $certificate->validation_hash) }}"
           target="_blank"
           rel="noopener">
            Verificar autenticidade
        </a>
    @else
        <div class="ds-cert-pending" dusk="certificate-unavailable">
            <div class="fw-semibold mb-1">Certificado ainda não disponível</div>
            <div class="ds-caption text-secondary">
                @if($isRevoked)
                    Este certificado foi revogado pela organização e não pode mais ser baixado. Fale com a secretaria do curso para saber mais.
                @elseif($progressPercentage !== null)
                    Você concluiu {{ (int) $progressPercentage }}% do curso. O certificado é emitido quando você cumpre as regras de conclusão.
                @else
                    O certificado é emitido quando você cumpre as regras de conclusão.
                @endif
            </div>
        </div>
    @endif
</x-ui.card>
