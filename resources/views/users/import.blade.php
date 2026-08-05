@extends('layouts.app')

@section('content')
    <x-ui.card title="Importar Alunos via CSV" kicker="Alunos & Gestores">
        <p style="font-size: 13px; color: var(--color-neutral-600); margin: 0 0 20px;">
            O arquivo é processado em lotes de 50 linhas diretamente no navegador — cada lote é
            enviado separadamente, sem limite de tamanho de arquivo no servidor. Cabeçalhos
            esperados: <code>name,email,cpf</code> (a coluna <code>cpf</code> é opcional).
        </p>

        <form dusk="csv-import-form" data-chunk-url="{{ route('users.import.chunk') }}" style="display: flex; flex-direction: column; gap: 20px; max-width: 560px;">
            <x-ui.select
                name="course_id"
                label="Curso de Destino"
                required
                :options="$courses->pluck('title', 'id')->all()"
                dusk="csv-course-select"
            />

            <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                <label for="csv-file" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Arquivo CSV</label>
                <input type="file" id="csv-file" name="csv_file" accept=".csv,text/csv" dusk="csv-file-input"
                       style="border-radius: 0px; border: 1px solid var(--color-divider); padding: 8px 12px; background: var(--color-surface); color: var(--color-text);" />
            </div>

            <div dusk="csv-import-progress-wrapper" style="display: none; flex-direction: column; gap: 6px;">
                <div style="width: 100%; height: 8px; background: var(--color-divider); border-radius: 0px;">
                    <div dusk="csv-import-progress-bar" style="width: 0%; height: 100%; background: var(--color-accent); transition: width 0.2s ease;"></div>
                </div>
                <span dusk="csv-import-progress-text" style="font-size: 12px; color: var(--color-neutral-600);"></span>
            </div>

            <div dusk="csv-import-results" style="display: none; font-size: 13px;"></div>

            <div style="display: flex; gap: 12px;">
                <x-ui.button type="submit" dusk="csv-import-submit">Iniciar Importação</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('users.index') }}">Voltar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
