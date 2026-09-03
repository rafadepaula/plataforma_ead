/**
 * PdfViewer - Visualizador de PDF da sala de aula (modo normal + tela cheia).
 *
 * Monta um `PdfViewerController` por container `[data-pdf-viewer]`
 * (bytes autenticados do endpoint gated renderizados em `<canvas>` via
 * pdf.js, sem text layer, sem URL de documento no DOM). A montagem é
 * passiva: o chunk do pdf.js só baixa nas aulas que têm PDF.
 */
import { PdfViewerController } from './pdf-viewer/PdfViewerController';

export class PdfViewer {
    constructor(httpClient) {
        this.httpClient = httpClient;
        this.controllers = [];
    }

    init() {
        if (typeof document === 'undefined') {
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        document.querySelectorAll('[data-pdf-viewer]').forEach((container) => {
            if (container.dataset.pdfViewerBound) {
                return;
            }

            container.dataset.pdfViewerBound = 'true';

            const controller = new PdfViewerController(container, this.httpClient);
            controller.mount();
            this.controllers.push(controller);
        });
    }
}

export default PdfViewer;
