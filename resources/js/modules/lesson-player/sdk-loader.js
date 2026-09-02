/**
 * Injeção idempotente dos SDKs de terceiros (YouTube IFrame API, Vimeo
 * Player SDK). O player é click-to-load: nada aqui roda até o primeiro
 * clique na fachada — a página de aula não baixa um byte de JS de
 * terceiro antes da interação do aluno.
 */

const YOUTUBE_IFRAME_API_SRC = 'https://www.youtube.com/iframe_api';
const VIMEO_PLAYER_SDK_SRC = 'https://player.vimeo.com/api/player.js';

const loadedScripts = new Map();

/**
 * Injeta `<script src>` uma única vez por documento; chamadas repetidas
 * devolvem a mesma Promise.
 */
export function loadScript(src) {
    if (!loadedScripts.has(src)) {
        loadedScripts.set(src, new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.addEventListener('load', () => resolve());
            script.addEventListener('error', () => {
                loadedScripts.delete(src);
                reject(new Error(`Falha ao carregar o player de vídeo (${src}).`));
            });
            document.head.appendChild(script);
        }));
    }

    return loadedScripts.get(src);
}

/**
 * YouTube IFrame API: a injeção sinaliza prontidão pelo callback global
 * `onYouTubeIframeAPIReady`, que é encadeado (não substituído) caso
 * outro script da página também dependa dele.
 */
export function loadYouTubeIframeApi() {
    if (window.YT && window.YT.Player) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const previousCallback = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (typeof previousCallback === 'function') previousCallback();
            resolve();
        };

        loadScript(YOUTUBE_IFRAME_API_SRC).catch(reject);
    });
}

export function loadVimeoPlayerSdk() {
    if (window.Vimeo && window.Vimeo.Player) {
        return Promise.resolve();
    }

    return loadScript(VIMEO_PLAYER_SDK_SRC);
}
