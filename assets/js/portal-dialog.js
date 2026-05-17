window.MyvhPortalDialog = (function() {
    let queue = [];
    let active = false;
    let styleInjected = false;
    let activeElements = null;

    function getSiteName() {
        if (window.myvhPortal && window.myvhPortal.site_name) {
            return String(window.myvhPortal.site_name);
        }

        if (window.myvhPortal && window.myvhPortal.siteName) {
            return String(window.myvhPortal.siteName);
        }

        if (window.myvhCal && window.myvhCal.site_name) {
            return String(window.myvhCal.site_name);
        }

        if (window.myvhCal && window.myvhCal.siteName) {
            return String(window.myvhCal.siteName);
        }

        return document.title || 'Message';
    }

    function ensureStyles() {
        if (styleInjected) {
            return;
        }

        let style = document.createElement('style');
        style.id = 'myvh-portal-dialog-styles';
        style.textContent = [
            '.myvh-portal-dialog-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:auto;}',
            '.myvh-portal-dialog{width:min(480px,100%);background:#fff;border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.25);overflow:hidden;font-family:inherit;pointer-events:auto;}',
            '.myvh-portal-dialog__head{padding:14px 18px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#111827;}',
            '.myvh-portal-dialog__body{padding:18px;color:#1f2937;line-height:1.5;white-space:pre-wrap;}',
            '.myvh-portal-dialog__actions{padding:12px 18px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #e5e7eb;}',
            '.myvh-portal-dialog__btn{appearance:none;border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:8px;padding:8px 14px;cursor:pointer;font:inherit;}',
            '.myvh-portal-dialog__btn:focus{outline:2px solid #2271b1;outline-offset:2px;}',
            '.myvh-portal-dialog__btn--primary{background:#2271b1;border-color:#2271b1;color:#fff;}'
        ].join('');

        document.head.appendChild(style);
        styleInjected = true;
    }

    function closeCurrent(result) {
        if (!activeElements) {
            return;
        }

        let resolver = activeElements.resolve;
        let backdrop = activeElements.backdrop;
        let onKeydown = activeElements.onKeydown;

        document.removeEventListener('keydown', onKeydown);
        if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }

        activeElements = null;
        active = false;

        if (typeof resolver === 'function') {
            resolver(result);
        }

        processQueue();
    }

    function renderDialog(item) {
        ensureStyles();

        let isConfirm = item.type === 'confirm';
        let options = item.options || {};
        let titleText = String(options.title || getSiteName());

        let backdrop = document.createElement('div');
        backdrop.className = 'myvh-portal-dialog-backdrop';

        let dialog = document.createElement('div');
        dialog.className = 'myvh-portal-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');

        let head = document.createElement('div');
        head.className = 'myvh-portal-dialog__head';
        head.textContent = titleText;

        let body = document.createElement('div');
        body.className = 'myvh-portal-dialog__body';
        body.textContent = String(item.message || '');

        let actions = document.createElement('div');
        actions.className = 'myvh-portal-dialog__actions';

        let cancelButton = null;
        if (isConfirm) {
            cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'myvh-portal-dialog__btn';
            cancelButton.textContent = String(options.cancelText || 'Cancel');
            cancelButton.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                closeCurrent(false);
            });
            actions.appendChild(cancelButton);
        }

        let okButton = document.createElement('button');
        okButton.type = 'button';
        okButton.className = 'myvh-portal-dialog__btn myvh-portal-dialog__btn--primary';
        okButton.textContent = String(options.okText || 'OK');
        okButton.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            closeCurrent(true);
        });
        actions.appendChild(okButton);

        dialog.appendChild(head);
        dialog.appendChild(body);
        dialog.appendChild(actions);
        backdrop.appendChild(dialog);

        backdrop.addEventListener('click', function(event) {
            if (event.target !== backdrop) {
                return;
            }

            if (isConfirm) {
                closeCurrent(false);
                return;
            }

            closeCurrent(true);
        });

        let onKeydown = function(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeCurrent(isConfirm ? false : true);
                return;
            }

            if (event.key === 'Tab') {
                let focusable = isConfirm ? [cancelButton, okButton] : [okButton];
                let currentIndex = focusable.indexOf(document.activeElement);
                if (event.shiftKey) {
                    if (currentIndex <= 0) {
                        event.preventDefault();
                        focusable[focusable.length - 1].focus();
                    }
                    return;
                }

                if (currentIndex === focusable.length - 1) {
                    event.preventDefault();
                    focusable[0].focus();
                }
            }
        };

        document.addEventListener('keydown', onKeydown);
        document.body.appendChild(backdrop);

        activeElements = {
            resolve: item.resolve,
            backdrop: backdrop,
            onKeydown: onKeydown
        };

        window.setTimeout(function() {
            if (isConfirm && cancelButton) {
                cancelButton.focus();
                return;
            }

            okButton.focus();
        }, 0);
    }

    function processQueue() {
        if (active || queue.length === 0) {
            return;
        }

        active = true;
        renderDialog(queue.shift());
    }

    function show(type, message, options) {
        return new Promise(function(resolve) {
            queue.push({
                type: type,
                message: message,
                options: options || {},
                resolve: resolve
            });

            processQueue();
        });
    }

    return {
        alert: function(message, options) {
            return show('alert', message, options);
        },
        confirm: function(message, options) {
            return show('confirm', message, options);
        }
    };
})();
