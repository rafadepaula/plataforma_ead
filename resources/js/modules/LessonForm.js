/**
 * LessonForm - interatividade do formulário de lição (módulo e lição).
 *
 * Substitui o antigo `<script>` inline da view (proibido pela convenção do
 * Bootstrap: interatividade vive em módulos JS). É puramente presenteacional
 * e de conveniência — o servidor (StoreLessonRequest/UpdateLessonRequest +
 * VideoUrlSanitizerManager) continua sendo a fonte da verdade.
 *
 * Responsabilidades:
 *  - alternar os campos de conteúdo conforme o `type` (quiz oculta mídia);
 *  - pré-visualizar o vídeo ao vivo por provedor (YouTube | Vimeo): a mesma
 *    heurística best-effort dos sanitizadores decide iframe vs. estado vazio,
 *    e o select de provedor acompanha a URL colada quando ela só casa com o
 *    outro provedor;
 *  - validar tamanho por arquivo no cliente (data-max-size em bytes), listar
 *    anexos escolhidos (nome, KB/MB, barra de progresso animada durante o
 *    POST, remoção individual) e marcar `.is-invalid` na zona ao exceder;
 *  - esconder anexos persistidos removidos no cliente somando
 *    `removed_media[]` ao form (o servidor apaga o registro e o arquivo).
 */
const VIDEO_PATTERNS = {
    youtube: /^https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$/i,
    vimeo: /^https?:\/\/(?:www\.)?(?:player\.)?vimeo\.com\/(?:video\/)?(\d{6,})(?:\/([A-Za-z0-9]+))?(?:[&?][^\s]*)?$/i,
};

// Espelha a canonicalização dos sanitizadores do servidor: o preview nunca
// aponta para uma forma que o embed do provedor recusaria.
const PREVIEW_BUILDERS = {
    youtube: (match) => `https://www.youtube-nocookie.com/embed/${match[1]}`,
    vimeo: (match, url) => {
        const hash = match[2] || new URL(url).searchParams.get('h');

        return `https://player.vimeo.com/video/${match[1]}${hash ? `?h=${hash}` : ''}`;
    },
};

const HINT_PUBLISHED = 'A lição fica visível para os alunos imediatamente após salvar.';
const HINT_UNPUBLISHED = 'A lição continua oculta para os alunos até ser publicada.';

export class LessonForm {
    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        this.bindTypeToggle();
        this.bindVideoPreview();
        this.bindFileDrops();
        this.bindPublishHint();
    }

    /**
     * Selecionar "Quiz" oculta os campos de conteúdo multimídia.
     */
    bindTypeToggle() {
        const typeSelect = document.querySelector('[data-lesson-type-select]');
        const contentFields = document.querySelector('[data-lesson-content-fields]');
        if (!typeSelect || !contentFields) return;

        const toggle = () => {
            contentFields.classList.toggle('d-none', typeSelect.value === 'quiz');
        };

        typeSelect.addEventListener('change', toggle);
        toggle();
    }

    /**
     * Pré-visualização 16:9 ao vivo por provedor: iframe sanitizado ou
     * estado vazio pastel. Quando a URL colada só casa com o OUTRO provedor,
     * o select acompanha a detecção (o servidor revalida no submit).
     */
    bindVideoPreview() {
        const field = document.querySelector('[data-video-field]');
        if (!field) return;

        const input = field.querySelector('input[name="video_url"]');
        const providerSelect = field.querySelector('[data-video-provider-select]');
        const frame = field.querySelector('[data-video-frame]');
        const emptyState = field.querySelector('[data-video-empty]');
        if (!input || !frame || !emptyState) return;

        const update = () => {
            const url = input.value.trim();
            let provider = providerSelect ? providerSelect.value : null;
            let match = provider && VIDEO_PATTERNS[provider] ? url.match(VIDEO_PATTERNS[provider]) : null;

            if (!match) {
                for (const [candidate, pattern] of Object.entries(VIDEO_PATTERNS)) {
                    if (candidate !== provider && pattern.test(url)) {
                        provider = candidate;
                        if (providerSelect) providerSelect.value = candidate;
                        match = url.match(pattern);
                        break;
                    }
                }
            }

            if (match && provider) {
                frame.src = PREVIEW_BUILDERS[provider](match, url);
                frame.classList.remove('d-none');
                emptyState.classList.add('d-none');
            } else {
                frame.removeAttribute('src');
                frame.classList.add('d-none');
                emptyState.classList.remove('d-none');
            }
        };

        input.addEventListener('input', update);
        if (providerSelect) providerSelect.addEventListener('change', update);
        update();
    }

    /**
     * Dropzones `[data-file-drop]`: validação de tamanho por arquivo,
     * listagem client-side e remoção de anexos (persistidos e recém-escolhidos).
     */
    bindFileDrops() {
        document.querySelectorAll('[data-file-drop]').forEach((root) => this.bindFileDrop(root));
    }

    bindFileDrop(root) {
        const zone = root.querySelector('[data-file-drop-zone]');
        const input = root.querySelector('input[type="file"]');
        const list = root.querySelector('[data-file-list]');
        if (!zone || !input || !list) return;

        const maxBytes = Number(root.getAttribute('data-max-size')) || 0;
        const feedback = root.querySelector('.invalid-feedback');
        let selected = [];

        const setError = (message) => {
            zone.classList.toggle('is-invalid', Boolean(message));
            if (feedback && message) feedback.textContent = message;
        };

        const formatSize = (bytes) => {
            const mb = bytes / (1024 * 1024);
            return mb >= 1
                ? `${mb.toFixed(1).replace('.', ',')} MB`
                : `${(bytes / 1024).toFixed(1).replace('.', ',')} KB`;
        };

        const syncInput = () => {
            try {
                const transfer = new DataTransfer();
                selected.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
            } catch (error) {
                // Navegadores sem DataTransfer construtível: o input mantém
                // a seleção original — o servidor revalida de qualquer forma.
            }
        };

        const renderSelected = () => {
            list.querySelectorAll('[data-client-file]').forEach((item) => item.remove());
            selected.forEach((file, index) => {
                const item = document.createElement('li');
                item.setAttribute('data-file-item', '');
                item.setAttribute('data-client-file', '');
                item.setAttribute('data-file-index', String(index));
                item.className = 'd-flex align-items-center gap-2 border rounded p-2';

                const name = document.createElement('span');
                name.className = 'text-truncate';
                name.textContent = file.name;

                const size = document.createElement('span');
                size.className = 'form-text mt-0 flex-shrink-0';
                size.textContent = formatSize(file.size);

                // POST clássico multipart: a barra é indeterminada/animada
                // durante o submit — não há XHR de upload para medir.
                const progress = document.createElement('div');
                progress.className = 'progress w-100';
                progress.setAttribute('role', 'progressbar');
                progress.setAttribute('aria-label', `Envio de ${file.name}`);
                progress.setAttribute('aria-valuemin', '0');
                progress.setAttribute('aria-valuenow', '100');
                progress.setAttribute('aria-valuemax', '100');
                const bar = document.createElement('div');
                bar.className = 'progress-bar progress-bar-striped progress-bar-animated w-100';
                progress.appendChild(bar);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-ghost btn-sm ms-auto';
                remove.setAttribute('data-remove-file', '');
                remove.setAttribute('aria-label', `Remover anexo ${file.name}`);
                remove.textContent = 'Remover';

                item.append(name, size, progress, remove);
                list.appendChild(item);
            });
        };

        const handleFiles = (fileList) => {
            const accepted = [];
            const rejected = [];

            Array.from(fileList).forEach((file) => {
                if (maxBytes && file.size > maxBytes) {
                    rejected.push(file);
                } else {
                    accepted.push(file);
                }
            });

            if (accepted.length > 0) {
                selected = selected.concat(accepted);
                syncInput();
                renderSelected();
            }

            if (rejected.length > 0) {
                const limitMb = Math.round((maxBytes / (1024 * 1024)) * 10) / 10;
                setError(
                    rejected.length === 1
                        ? `"${rejected[0].name}" excede o limite de ${String(limitMb).replace('.', ',')} MB por arquivo.`
                        : `${rejected.length} arquivos excedem o limite de ${String(limitMb).replace('.', ',')} MB por arquivo.`
                );
            } else {
                setError('');
            }
        };

        input.addEventListener('change', () => handleFiles(input.files));

        zone.addEventListener('dragover', (event) => event.preventDefault());
        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                handleFiles(event.dataTransfer.files);
            }
        });

        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-file]');
            if (!button) return;

            const item = button.closest('[data-file-item]');
            if (!item) return;

            const attachmentId = item.getAttribute('data-attachment-id');
            if (attachmentId) {
                // Anexo persistido: esconder no cliente e pedir a exclusão
                // via removed_media[] — o servidor valida a posse e apaga o
                // registro e o arquivo.
                const form = item.closest('form');
                if (form) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'removed_media[]';
                    hidden.value = attachmentId;
                    form.appendChild(hidden);
                }
                item.classList.add('d-none');
                return;
            }

            const indexAttr = item.getAttribute('data-file-index');
            const index = indexAttr === null ? -1 : Number(indexAttr);
            if (index < 0 || index >= selected.length) return;

            selected = selected.filter((_, fileIndex) => fileIndex !== index);
            syncInput();
            renderSelected();
        });
    }

    /**
     * Hint reativo do interruptor "Publicado".
     */
    bindPublishHint() {
        const hint = document.querySelector('[data-publish-hint]');
        const switchInput = document.querySelector('input[name="is_published"]');
        if (!hint || !switchInput) return;

        switchInput.addEventListener('change', () => {
            hint.textContent = switchInput.checked ? HINT_PUBLISHED : HINT_UNPUBLISHED;
        });
    }
}

export default LessonForm;
