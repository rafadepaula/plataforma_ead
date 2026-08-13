@extends('layouts.app')

@section('content')
    <x-ui.card title="Importar Alunos via CSV" kicker="Alunos & Gestores">
        <p class="small text-body-secondary mb-4">
            O arquivo é processado em lotes de 50 linhas diretamente no navegador — cada lote é
            enviado separadamente, sem limite de tamanho de arquivo no servidor. Cabeçalhos
            esperados: <code>name,email,cpf</code> (a coluna <code>cpf</code> é opcional).
        </p>

        <form dusk="csv-import-form" data-chunk-url="{{ route('users.import.chunk') }}" class="max-w-560">
            <x-ui.field-stack>
                <x-ui.select
                    name="course_id"
                    label="Curso de Destino"
                    required
                    :options="$courses->pluck('title', 'id')->all()"
                    dusk="csv-course-select"
                />

                <div class="mb-3">
                    <label for="csv-file" class="form-label">Arquivo CSV</label>
                    <input type="file" id="csv-file" name="csv_file" accept=".csv,text/csv"
                           class="form-control" dusk="csv-file-input">
                </div>
            </x-ui.field-stack>

            {{--
                Estado oculto = utility `.d-none` (nunca `style.display`, nunca o
                atributo nativo `hidden`: o Reboot do Bootstrap emite
                `[hidden] { display: none !important }` e venceria uma declaração
                inline sem `!important`).

                Contrato para `resources/js/modules/CsvImporter.js`:
                  - revelar   → wrapper.classList.remove('d-none')
                  - progresso → [dusk="csv-import-progress-bar"] (ou
                                [data-progress-bar]) recebe style.width, e o
                                wrapper .progress mantém aria-valuenow sincronizado.
            --}}
            <div dusk="csv-import-progress-wrapper" class="d-none mb-3">
                <x-ui.progress
                    :value="0"
                    height="8"
                    label="Progresso da importação"
                    class="mb-2"
                    dusk="csv-import-progress"
                />
                <span dusk="csv-import-progress-text" class="small text-body-secondary"></span>
            </div>

            {{--
                Idem: `CsvImporter.js` revela com classList.remove('d-none') e
                sinaliza erro com a classe utilitária `.text-primary` (o accent
                #ec3013 do tema, mesma cor do antigo var(--color-accent)),
                removendo-a no caminho de sucesso.
            --}}
            <div dusk="csv-import-results" class="d-none small"></div>

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="csv-import-submit">Iniciar Importação</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('users.index') }}">Voltar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
