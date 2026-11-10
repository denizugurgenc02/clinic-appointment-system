/**
 * Ortak yardımcılar. AJAX istekleri CSRF jetonunu <meta name="csrf-token"> etiketinden alır.
 */
'use strict';

window.App = {
    csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /**
     * JSON döndüren uç noktaya istek atar. Beklenmeyen her durumda (ağ hatası, oturum süresi
     * dolması, JSON olmayan yanıt, sunucu hatası) kullanıcıya gösterilebilecek sade bir mesajla
     * Error fırlatır; teknik ayrıntı (yığın izi, ayrıştırma hatası) arayüze yansımaz.
     */
    async fetchJson(url, options = {}) {
        let response;
        try {
            response = await fetch(url, {
                ...options,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': this.csrfToken(),
                    ...(options.headers || {}),
                },
            });
        } catch (networkError) {
            throw new Error('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.');
        }

        const isJson = (response.headers.get('Content-Type') || '').includes('application/json');
        let data = null;
        if (isJson) {
            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }
        }

        if (response.status === 401 || (response.redirected && !isJson)) {
            throw new Error('Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar giriş yapın.');
        }

        if (!response.ok || data === null) {
            const message = data && typeof data.error === 'string'
                ? data.error
                : 'Beklenmeyen bir hata oluştu. Lütfen biraz sonra tekrar deneyin.';
            throw new Error(message);
        }

        return data;
    },
};
