import { VimeoAdapter } from './VimeoAdapter';
import { YoutubeAdapter } from './YoutubeAdapter';

/**
 * Fábrica de adapters por `data-provider`. Um novo provedor é um adapter
 * novo + uma entrada aqui — controles, fachada e tracking não mudam.
 */
const ADAPTERS = {
    youtube: YoutubeAdapter,
    vimeo: VimeoAdapter,
};

export function createAdapter(provider, container, options = {}) {
    const AdapterClass = ADAPTERS[provider];

    if (!AdapterClass) {
        throw new Error(`Provedor de vídeo não suportado: "${provider}".`);
    }

    return new AdapterClass(container, options);
}

export default createAdapter;
