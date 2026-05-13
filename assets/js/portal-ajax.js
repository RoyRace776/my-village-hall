window.MyvhPortalAjax = (function() {
    function getConfig(preferredScope) {
        if (preferredScope === 'portal' && window.myvhPortal) {
            return {
                ajaxUrl: myvhPortal.ajax_url,
                nonce: myvhPortal.nonce
            };
        }

        if (preferredScope === 'calendar' && window.myvhCal) {
            return {
                ajaxUrl: myvhCal.ajax_url,
                nonce: myvhCal.nonce
            };
        }

        if (window.myvhPortal) {
            return {
                ajaxUrl: myvhPortal.ajax_url,
                nonce: myvhPortal.nonce
            };
        }

        if (window.myvhCal) {
            return {
                ajaxUrl: myvhCal.ajax_url,
                nonce: myvhCal.portalNonce || myvhCal.nonce
            };
        }

        throw new Error('Portal AJAX config missing');
    }

    function appendRequestId(formData) {
        formData.append('request_id', 'myvh_' + Date.now() + '_' + Math.random().toString(16).slice(2));
    }

    function post(action, payload, options) {
        let scope = (options && options.scope) || 'portal';
        let config = getConfig(scope);
        let formData = (payload instanceof HTMLFormElement) ? new FormData(payload) : new FormData();

        if (!(payload instanceof HTMLFormElement) && payload && typeof payload === 'object') {
            Object.keys(payload).forEach(function(key) {
                formData.append(key, payload[key]);
            });
        }

        formData.append('action', action);
        formData.append('nonce', config.nonce);
        appendRequestId(formData);

        return fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData
        }).then(function(response) {
            return response.json();
        });
    }

    function get(params, options) {
        let scope = (options && options.scope) || 'portal';
        let config = getConfig(scope);
        let query = new URLSearchParams(params || {});

        if (!query.has('nonce')) {
            query.set('nonce', config.nonce);
        }

        return fetch(config.ajaxUrl + '?' + query.toString()).then(function(response) {
            return response.json();
        });
    }

    return {
        post: post,
        get: get
    };
})();
