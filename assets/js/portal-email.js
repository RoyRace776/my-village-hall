window.MyvhPortalEmail = (function() {
    function portalConfirm(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.confirm === 'function') {
            return window.MyvhPortalDialog.confirm(message);
        }

        return Promise.resolve(window.confirm(message));
    }

    function showMessage(target, text, isError) {
        if (!target) {
            return;
        }

        target.textContent = text || '';
        target.style.color = isError ? '#b32d2e' : '#2d5a27';
    }

    function withEditorContent(textarea, callback) {
        if (!textarea || typeof callback !== 'function') {
            return;
        }

        const editor = window.tinymce ? window.tinymce.get(textarea.id) : null;
        if (editor) {
            callback(editor.getContent(), editor);
            return;
        }

        callback(textarea.value, null);
    }

    function initEmailTemplatesPage() {
        const page = document.querySelector('.myvh-email-templates-page');
        if (!page || page.dataset.bound === '1') {
            return;
        }

        page.dataset.bound = '1';

        page.addEventListener('submit', function(event) {
            const form = event.target.closest('form[data-email-template-reset="1"]');
            if (!form) {
                return;
            }

            event.preventDefault();

            const slug = form.getAttribute('data-template-slug') || '';
            if (!slug) {
                return;
            }

            const confirmMessage = form.getAttribute('data-confirm') || '';
            const messageTargetId = form.getAttribute('data-message-target') || '';
            const message = messageTargetId ? document.getElementById(messageTargetId) : null;

            portalConfirm(confirmMessage || 'Reset this template to default?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                showMessage(message, 'Resetting...', false);

                return window.MyvhPortalAjax.post('myvh_reset_email_template', { template: slug }, { scope: 'portal' })
                .then(function(res) {
                    if (!res.success) {
                        showMessage(message, 'Failed to reset template', true);
                        return;
                    }

                    showMessage(message, res.data && res.data.message ? res.data.message : 'Template reset', false);
                    window.location.hash = '#email-templates?refresh=' + Date.now();
                })
                .catch(function() {
                    showMessage(message, 'Unexpected error while resetting template', true);
                });
            });
        });
    }

    function initEmailTemplateEditPage() {
        const page = document.querySelector('.myvh-email-template-edit-page');
        if (!page) {
            return;
        }

        const form = page.querySelector('#myvh-email-template-form');
        const message = page.querySelector('#myvh-email-template-message');
        const textarea = page.querySelector('#myvh-email-template-body');
        const modal = page.querySelector('#myvh-email-template-preview-modal');
        const previewContent = page.querySelector('[data-email-preview-content]');

        if (!form || !textarea) {
            return;
        }

        const initEditor = function() {
            if (!window.tinymce) {
                return;
            }

            window.tinymce.remove('#' + textarea.id);
            window.tinymce.init({
                selector: '#' + textarea.id,
                menubar: false,
                branding: false,
                height: 420,
                plugins: 'link lists',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
                convert_urls: false
            });
        };

        initEditor();

        page.querySelectorAll('[data-email-placeholder]').forEach(function(button) {
            button.addEventListener('click', function() {
                const token = button.getAttribute('data-email-placeholder') || '';
                if (!token) {
                    return;
                }

                const editor = window.tinymce ? window.tinymce.get(textarea.id) : null;
                if (editor) {
                    editor.focus();
                    editor.insertContent(token);
                    return;
                }

                const start = textarea.selectionStart || 0;
                const end = textarea.selectionEnd || 0;
                const value = textarea.value || '';
                textarea.value = value.slice(0, start) + token + value.slice(end);
                textarea.focus();
                const next = start + token.length;
                textarea.setSelectionRange(next, next);
            });
        });

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const data = new FormData(form);
            const slug = String(data.get('template') || '');
            const subject = String(data.get('subject') || '').trim();

            withEditorContent(textarea, function(htmlBody) {
                if (!subject || !String(htmlBody || '').trim()) {
                    showMessage(message, 'Subject and body are required', true);
                    return;
                }

                showMessage(message, 'Saving...', false);

                window.MyvhPortalAjax.post('myvh_save_email_template', {
                    template: slug,
                    subject: subject,
                    html_body: htmlBody
                }, { scope: 'portal' })
                    .then(function(res) {
                        if (!res.success) {
                            showMessage(message, 'Failed to save template', true);
                            return;
                        }

                        showMessage(message, res.data && res.data.message ? res.data.message : 'Template saved', false);
                    })
                    .catch(function() {
                        showMessage(message, 'Unexpected error while saving template', true);
                    });
            });
        });

        const previewButton = page.querySelector('[data-email-template-preview="1"]');
        if (previewButton) {
            previewButton.addEventListener('click', function() {
                const data = new FormData(form);
                const slug = String(data.get('template') || '');
                const subject = String(data.get('subject') || '').trim();

                withEditorContent(textarea, function(htmlBody) {
                    showMessage(message, 'Building preview...', false);

                    window.MyvhPortalAjax.post('myvh_preview_email_template', {
                        template: slug,
                        subject: subject,
                        html_body: htmlBody
                    }, { scope: 'portal' })
                        .then(function(res) {
                            if (!res.success) {
                                showMessage(message, 'Failed to preview template', true);
                                return;
                            }

                            if (previewContent) {
                                const subjectHtml = (res.data && res.data.subject ? String(res.data.subject) : '')
                                    .replace(/&/g, '&amp;')
                                    .replace(/</g, '&lt;')
                                    .replace(/>/g, '&gt;');
                                previewContent.innerHTML = '<h4>' + subjectHtml + '</h4>' + (res.data && res.data.html ? res.data.html : '');
                            }

                            if (modal) {
                                modal.hidden = false;
                            }

                            showMessage(message, '', false);
                        })
                        .catch(function() {
                            showMessage(message, 'Unexpected error while previewing template', true);
                        });
                });
            });
        }

        const sendTestButton = page.querySelector('[data-email-template-send-test="1"]');
        if (sendTestButton) {
            sendTestButton.addEventListener('click', function() {
                const data = new FormData(form);
                const slug = String(data.get('template') || '');
                const subject = String(data.get('subject') || '').trim();

                withEditorContent(textarea, function(htmlBody) {
                    showMessage(message, 'Sending test email...', false);
                    sendTestButton.disabled = true;

                    window.MyvhPortalAjax.post('myvh_send_test_email_template', {
                        template: slug,
                        subject: subject,
                        html_body: htmlBody
                    }, { scope: 'portal' })
                        .then(function(res) {
                            sendTestButton.disabled = false;
                            if (!res.success) {
                                showMessage(message, (res.data && res.data.message) ? res.data.message : 'Failed to send test email', true);
                                return;
                            }

                            showMessage(message, (res.data && res.data.message) ? res.data.message : 'Test email sent', false);
                        })
                        .catch(function() {
                            sendTestButton.disabled = false;
                            showMessage(message, 'Unexpected error while sending test email', true);
                        });
                });
            });
        }

        const resetButton = page.querySelector('.myvh-account-actions [data-email-template-reset="1"]');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                const slug = page.getAttribute('data-template-slug') || '';
                if (!slug) {
                    return;
                }

                portalConfirm('Reset this template to default?').then(function(confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    showMessage(message, 'Resetting...', false);

                    return window.MyvhPortalAjax.post('myvh_reset_email_template', { template: slug }, { scope: 'portal' })
                    .then(function(res) {
                        if (!res.success) {
                            showMessage(message, 'Failed to reset template', true);
                            return;
                        }

                        showMessage(message, res.data && res.data.message ? res.data.message : 'Template reset', false);
                        window.location.hash = '#email-template-edit?slug=' + encodeURIComponent(slug) + '&refresh=' + Date.now();
                    })
                    .catch(function() {
                        showMessage(message, 'Unexpected error while resetting template', true);
                    });
                });
            });
        }

        page.addEventListener('click', function(event) {
            const closeTrigger = event.target.closest('[data-email-preview-close="1"]');
            if (closeTrigger && modal) {
                modal.hidden = true;
            }
        });
    }

    return {
        initEmailTemplatesPage: initEmailTemplatesPage,
        initEmailTemplateEditPage: initEmailTemplateEditPage
    };
})();
