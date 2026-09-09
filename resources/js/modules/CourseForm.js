/**
 * CourseForm - interatividade do campo de capa do curso (edição).
 *
 * Espelha as convenções de `LessonForm.js` (módulo presenteacional, sem
 * `<script>` inline na view): é puramente conveniência — o servidor
 * continua sendo a fonte da verdade para tipo, tamanho e remoção.
 *
 * Responsabilidades:
 *  - validar o tamanho do arquivo no cliente (`data-max-size` em bytes),
 *    marcando `.is-invalid` e escrevendo no `.invalid-feedback` existente
 *    (convenção de validação — nunca cria markup de erro novo);
 *  - pré-visualizar a capa ao vivo via object URL no
 *    `dusk="course-cover-preview"` (atualiza o `<img>` persistido ou cria
 *    um temporário quando o curso ainda não tem capa);
 *  - marcar "Remover capa atual" limpa o file input e esconde a
 *    pré-visualização, e escolher um arquivo desmarca a remoção.
 */
export class CourseForm {
    constructor() {}

    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        document.querySelectorAll('[data-course-cover]').forEach((root) => this.bindCoverField(root));
    }

    bindCoverField(root) {
        const input = root.querySelector('[data-course-cover-input]');
        if (!input) return;

        const zone = root.querySelector('[data-course-cover-zone]');
        const previewWrap = root.querySelector('[data-course-cover-preview-wrap]');
        const removeCheckbox = root.querySelector('[data-course-cover-remove]');
        const maxBytes = Number(input.getAttribute('data-max-size')) || 0;
        const feedback = root.querySelector('.invalid-feedback');
        let objectUrl = null;

        const setError = (message) => {
            input.classList.toggle('is-invalid', Boolean(message));
            if (zone) zone.classList.toggle('is-invalid', Boolean(message));
            if (feedback && message) feedback.textContent = message;
        };

        const currentPreview = () => root.querySelector('[data-course-cover-preview]');

        const revokeObjectUrl = () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        };

        const showPreview = (file) => {
            revokeObjectUrl();
            objectUrl = URL.createObjectURL(file);

            let preview = currentPreview();
            if (!preview && previewWrap) {
                preview = document.createElement('img');
                preview.setAttribute('dusk', 'course-cover-preview');
                preview.setAttribute('data-course-cover-preview', '');
                preview.setAttribute('alt', 'Pré-visualização da capa');
                preview.className = 'img-fluid rounded border mb-2';
                preview.dataset.clientPreview = 'true';
                previewWrap.appendChild(preview);
            }

            if (preview) {
                if (preview.dataset.clientPreview !== 'true' && !preview.dataset.originalSrc) {
                    preview.dataset.originalSrc = preview.getAttribute('src') || '';
                }
                preview.src = objectUrl;
                preview.classList.remove('d-none');
            }
        };

        const hidePreview = () => {
            revokeObjectUrl();
            const preview = currentPreview();
            if (!preview) return;

            if (preview.dataset.clientPreview === 'true') {
                preview.remove();
            } else {
                if (preview.dataset.originalSrc) {
                    preview.setAttribute('src', preview.dataset.originalSrc);
                    delete preview.dataset.originalSrc;
                }
                preview.classList.add('d-none');
            }
        };

        const restorePersistedPreview = () => {
            const preview = currentPreview();
            if (preview && preview.dataset.clientPreview !== 'true') {
                preview.classList.remove('d-none');
            }
        };

        input.addEventListener('change', () => {
            const file = input.files ? input.files[0] : null;
            if (!file) return;

            if (maxBytes && file.size > maxBytes) {
                const limitMb = Math.round((maxBytes / (1024 * 1024)) * 10) / 10;
                input.value = '';
                setError(`"${file.name}" excede o limite de ${String(limitMb).replace('.', ',')} MB.`);
                return;
            }

            setError('');
            if (removeCheckbox) removeCheckbox.checked = false;
            showPreview(file);
        });

        if (removeCheckbox) {
            removeCheckbox.addEventListener('change', () => {
                if (removeCheckbox.checked) {
                    input.value = '';
                    setError('');
                    hidePreview();
                } else {
                    restorePersistedPreview();
                }
            });
        }
    }
}

export default CourseForm;
