/**
 * Ortak yardımcılar. AJAX istekleri CSRF jetonunu <meta name="csrf-token"> etiketinden alır.
 */
'use strict';

window.App = {
    csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /** JSON döndüren uç noktaya istek atar. */
    async fetchJson(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-Token': this.csrfToken(),
                ...(options.headers || {}),
            },
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'İstek başarısız oldu.');
        }
        return data;
    },
};
