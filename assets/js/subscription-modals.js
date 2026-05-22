/**
 * MyvhSubscriptionModals
 *
 * Provides upgrade/subscription-required modals for the portal and admin.
 *
 * Triggered by:
 *   window.MyvhSubscriptionModals.show(trigger, data)
 *
 * trigger values:
 *   'trial_expired'    — free trial has ended
 *   'usage_limit'      — booking limit reached
 *   'feature_locked'   — feature not on current plan
 *   'past_due'         — payment overdue
 *   'subscription_missing' — no subscription found
 */

window.MyvhSubscriptionModals = (function () {
    'use strict';

    var _upgradeUrl = (window.myvhSubscription && window.myvhSubscription.upgrade_url)
        ? window.myvhSubscription.upgrade_url
        : '';

    var _modalEl = null;

    var _messages = {
        trial_expired: {
            title: 'Your Free Trial Has Ended',
            body: 'Your free trial period has expired. Upgrade your plan to continue creating bookings and accessing all features.',
            cta: 'Upgrade Now',
        },
        usage_limit: {
            title: 'Booking Limit Reached',
            body: 'You have used all available bookings on your current plan for this period. Upgrade to a higher plan to create more bookings.',
            cta: 'Upgrade Plan',
        },
        feature_locked: {
            title: 'Feature Not Available',
            body: 'This feature is not included in your current subscription plan. Upgrade to unlock access.',
            cta: 'Upgrade Plan',
        },
        past_due: {
            title: 'Payment Required',
            body: 'Your subscription payment is overdue. Please update your payment details to continue using the service.',
            cta: 'Update Billing',
        },
        subscription_missing: {
            title: 'Subscription Required',
            body: 'An active subscription is required to perform this action. Please subscribe to continue.',
            cta: 'View Plans',
        },
    };

    function _getContent(trigger) {
        return _messages[trigger] || _messages['subscription_missing'];
    }

    function _buildModal() {
        var existing = document.getElementById('myvh-subscription-modal');
        if (existing) {
            return existing;
        }

        var overlay = document.createElement('div');
        overlay.id = 'myvh-subscription-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'myvh-subscription-modal-title');
        overlay.style.cssText = [
            'position:fixed',
            'top:0',
            'left:0',
            'width:100%',
            'height:100%',
            'background:rgba(0,0,0,0.55)',
            'z-index:99999',
            'display:flex',
            'align-items:center',
            'justify-content:center',
        ].join(';');

        var box = document.createElement('div');
        box.className = 'myvh-subscription-modal-box';
        box.style.cssText = [
            'background:#fff',
            'border-radius:6px',
            'padding:28px 32px',
            'max-width:440px',
            'width:90%',
            'box-shadow:0 8px 32px rgba(0,0,0,0.18)',
            'position:relative',
        ].join(';');

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.style.cssText = [
            'position:absolute',
            'top:12px',
            'right:16px',
            'background:none',
            'border:none',
            'font-size:22px',
            'cursor:pointer',
            'color:#666',
            'line-height:1',
        ].join(';');
        closeBtn.textContent = '\u00D7';
        closeBtn.addEventListener('click', close);

        var icon = document.createElement('div');
        icon.id = 'myvh-subscription-modal-icon';
        icon.style.cssText = 'font-size:36px;margin-bottom:12px;';

        var title = document.createElement('h2');
        title.id = 'myvh-subscription-modal-title';
        title.style.cssText = 'margin:0 0 10px;font-size:20px;';

        var body = document.createElement('p');
        body.id = 'myvh-subscription-modal-body';
        body.style.cssText = 'margin:0 0 20px;color:#444;line-height:1.5;';

        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:10px;justify-content:flex-end;';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.id = 'myvh-subscription-modal-cancel';
        cancelBtn.textContent = 'Dismiss';
        cancelBtn.style.cssText = [
            'padding:8px 18px',
            'border:1px solid #ccc',
            'background:#f8f8f8',
            'border-radius:4px',
            'cursor:pointer',
            'font-size:14px',
        ].join(';');
        cancelBtn.addEventListener('click', close);

        var upgradeBtn = document.createElement('a');
        upgradeBtn.id = 'myvh-subscription-modal-cta';
        upgradeBtn.style.cssText = [
            'padding:8px 18px',
            'background:#2271b1',
            'color:#fff',
            'border-radius:4px',
            'text-decoration:none',
            'font-size:14px',
            'font-weight:600',
            'display:inline-block',
        ].join(';');

        box.appendChild(closeBtn);
        box.appendChild(icon);
        box.appendChild(title);
        box.appendChild(body);
        actions.appendChild(cancelBtn);
        actions.appendChild(upgradeBtn);
        box.appendChild(actions);
        overlay.appendChild(box);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && _modalEl && !_modalEl.classList.contains('myvh-hidden')) {
                close();
            }
        });

        document.body.appendChild(overlay);
        return overlay;
    }

    function show(trigger, data) {
        var content = _getContent(trigger);
        _modalEl = _buildModal();

        var iconMap = {
            trial_expired: '\u23F3',
            usage_limit: '\uD83D\uDCCA',
            feature_locked: '\uD83D\uDD12',
            past_due: '\uD83D\uDCB3',
            subscription_missing: '\u26A0\uFE0F',
        };

        var iconEl = document.getElementById('myvh-subscription-modal-icon');
        if (iconEl) iconEl.textContent = iconMap[trigger] || '\u26A0\uFE0F';

        var titleEl = document.getElementById('myvh-subscription-modal-title');
        if (titleEl) titleEl.textContent = content.title;

        var bodyEl = document.getElementById('myvh-subscription-modal-body');
        if (bodyEl) bodyEl.textContent = content.body;

        var ctaEl = document.getElementById('myvh-subscription-modal-cta');
        if (ctaEl) {
            ctaEl.textContent = content.cta;
            ctaEl.href = _upgradeUrl || '#';
        }

        _modalEl.style.display = 'flex';

        var focusTarget = document.getElementById('myvh-subscription-modal-cta');
        if (focusTarget) {
            focusTarget.focus();
        }
    }

    function close() {
        if (_modalEl) {
            _modalEl.style.display = 'none';
        }
    }

    return {
        show: show,
        close: close,
    };
})();
