@props([
    'certificate' => null,
    'course' => null,
    'progressPercentage' => null,
])

<x-ui.card title="Certificado" {{ $attributes }}>
    @if($certificate)
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
                <div class="ds-caption text-secondary">
                    Certificado nº {{ $certificate->code ?? $certificate->validation_hash }}
                </div>
            </div>
        </div>

        <x-ui.button variant="primary"
                     :href="route('certificates.download', $certificate)"
                     dusk="download-certificate">
            Baixar certificado
        </x-ui.button>
    @else
        <div class="ds-cert-pending" dusk="certificate-unavailable">
            <div class="fw-semibold mb-1">
                Certificado indisponível.{{ $progressPercentage !== null ? ' ' . (int) $progressPercentage . '%' : '' }}
            </div>
            <div class="ds-caption text-secondary">
                @if($progressPercentage !== null)
                    Você concluiu {{ (int) $progressPercentage }}% do curso. O certificado é emitido quando você cumpre as regras de conclusão.
                @else
                    O certificado é emitido quando você cumpre as regras de conclusão.
                @endif
            </div>
        </div>
    @endif
</x-ui.card>
