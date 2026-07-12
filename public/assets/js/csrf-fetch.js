(function(window, document) {
    'use strict';

    function getMeta(name) {
        const element = document.querySelector(`meta[name="${name}"]`);
        return element ? element.getAttribute('content') || '' : '';
    }

    function getCookie(name) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getCsrfHeaderName() {
        return getMeta('csrf-header') || 'X-CSRF-TOKEN';
    }

    function getCsrfTokenName() {
        return getMeta('csrf-token-name') || 'csrf_token';
    }

    function getCsrfCookieName() {
        return getMeta('csrf-cookie-name') || 'csrf_cookie_name';
    }

    function getCsrfToken() {
        return getCookie(getCsrfCookieName()) || getMeta('csrf-hash') || '';
    }

    function isMutatingMethod(method) {
        return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method || 'GET').toUpperCase());
    }

    function normalizeHeaders(headers) {
        return new Headers(headers || {});
    }

    function shouldAttachBodyToken(headers, body) {
        if (!body) {
            return false;
        }

        const contentType = (headers.get('Content-Type') || '').toLowerCase();

        return body instanceof FormData || contentType.includes('application/x-www-form-urlencoded');
    }

    function attachBodyToken(body, tokenName, tokenValue) {
        if (!tokenValue) {
            return body;
        }

        if (body instanceof FormData) {
            if (!body.has(tokenName)) {
                body.append(tokenName, tokenValue);
            }
            return body;
        }

        if (body instanceof URLSearchParams) {
            if (!body.has(tokenName)) {
                body.append(tokenName, tokenValue);
            }
            return body;
        }

        if (typeof body === 'string') {
            const params = new URLSearchParams(body);
            if (!params.has(tokenName)) {
                params.append(tokenName, tokenValue);
            }
            return params.toString();
        }

        return body;
    }

    function secureFetch(url, options) {
        const requestOptions = Object.assign({}, options || {});
        const method = String(requestOptions.method || 'GET').toUpperCase();
        const headers = normalizeHeaders(requestOptions.headers);
        const csrfToken = getCsrfToken();
        const csrfHeaderName = getCsrfHeaderName();
        const csrfTokenName = getCsrfTokenName();

        headers.set('X-Requested-With', headers.get('X-Requested-With') || 'XMLHttpRequest');

        if (isMutatingMethod(method) && csrfToken) {
            headers.set(csrfHeaderName, csrfToken);

            if (shouldAttachBodyToken(headers, requestOptions.body)) {
                requestOptions.body = attachBodyToken(requestOptions.body, csrfTokenName, csrfToken);
            }
        }

        requestOptions.method = method;
        requestOptions.headers = headers;

        return window.fetch(url, requestOptions);
    }

    window.SupportPontoSecurity = {
        getCsrfToken,
        getCsrfTokenName,
        getCsrfCookieName,
        getCsrfHeaderName,
        secureFetch,
    };

    window.spFetch = secureFetch;
})(window, document);
