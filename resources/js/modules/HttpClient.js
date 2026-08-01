/**
 * HttpClient - SOLID JavaScript wrapper over fetch API with CSRF token & JSON parsing
 */
export class HttpClient {
    constructor(baseURL = '') {
        this.baseURL = baseURL;
    }

    /**
     * Extract CSRF token from DOM meta tag or cookies.
     */
    getCsrfToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta && tokenMeta.getAttribute('content')) {
            return tokenMeta.getAttribute('content');
        }
        const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    /**
     * Get default HTTP headers including CSRF token and JSON accept/content headers.
     */
    getDefaultHeaders() {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const csrfToken = this.getCsrfToken();
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
            headers['X-XSRF-TOKEN'] = csrfToken;
        }
        return headers;
    }

    /**
     * Core HTTP request execution method.
     */
    async request(url, options = {}) {
        const fullUrl = this.baseURL ? `${this.baseURL.replace(/\/$/, '')}/${url.replace(/^\//, '')}` : url;
        const headers = {
            ...this.getDefaultHeaders(),
            ...(options.headers || {}),
        };

        const config = {
            ...options,
            headers,
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(fullUrl, config);
            const contentType = response.headers.get('content-type');
            let data = null;

            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                const error = new Error((data && typeof data === 'object' && data.message) ? data.message : `HTTP error! Status: ${response.status}`);
                error.status = response.status;
                error.data = data;
                error.response = response;
                throw error;
            }

            return {
                ok: response.ok,
                status: response.status,
                data,
                headers: response.headers,
            };
        } catch (error) {
            if (typeof error.status === 'undefined') {
                error.status = 0;
            }
            throw error;
        }
    }

    async get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    }

    async post(url, data = null, options = {}) {
        return this.request(url, { ...options, method: 'POST', body: data });
    }

    async put(url, data = null, options = {}) {
        return this.request(url, { ...options, method: 'PUT', body: data });
    }

    async patch(url, data = null, options = {}) {
        return this.request(url, { ...options, method: 'PATCH', body: data });
    }

    async delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    }
}

export default new HttpClient();
