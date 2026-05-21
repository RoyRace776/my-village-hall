(function () {
    'use strict';

    function qsa(root, selector) {
        return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    function safeParseFloat(value) {
        if (value === '' || value === null || typeof value === 'undefined') {
            return null;
        }
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function buildWizard(form) {
        var steps = qsa(form, '.myvh-wizard-step');
        if (!steps.length) {
            return;
        }

        var stepLabel = document.getElementById('myvh-current-step-label');
        var prevButton = form.querySelector('[data-myvh-step-action="prev"]');
        var nextButton = form.querySelector('[data-myvh-step-action="next"]');
        var submitButton = form.querySelector('[data-myvh-step-action="submit"]');
        var setupPayloadInput = document.getElementById('myvh-setup-payload');
        var reviewBox = document.getElementById('myvh-site-request-review');
        var draftTokenInput = form.querySelector('[name="myvh_setup_draft_token"]');
        var nonceInput = form.querySelector('[name="myvh_create_site_nonce"]');
        var draftSaveTimer = 0;
        var currentStep = 0;

        function setStep(index) {
            currentStep = Math.max(0, Math.min(index, steps.length - 1));

            steps.forEach(function (step, i) {
                step.hidden = i !== currentStep;
            });

            if (stepLabel) {
                stepLabel.textContent = 'Step ' + (currentStep + 1) + ' of ' + steps.length;
            }

            if (prevButton) {
                prevButton.hidden = currentStep === 0;
            }

            if (nextButton) {
                nextButton.hidden = currentStep === steps.length - 1;
            }

            if (submitButton) {
                submitButton.hidden = currentStep !== steps.length - 1;
            }

            if (currentStep === steps.length - 1) {
                renderReview();
            }
        }

        function validateAdminPasswordConfirmation() {
            var adminPasswordInput = form.querySelector('[name="admin_password"]');
            var adminPasswordConfirmInput = form.querySelector('[name="admin_password_confirm"]');
            if (!adminPasswordInput || !adminPasswordConfirmInput) {
                return true;
            }

            if (adminPasswordInput.value !== adminPasswordConfirmInput.value) {
                adminPasswordConfirmInput.setCustomValidity('Admin password and confirmation must match.');
                adminPasswordConfirmInput.reportValidity();
                return false;
            }

            adminPasswordConfirmInput.setCustomValidity('');
            return true;
        }

        function validateVisibleRequiredFields() {
            var visibleStep = steps[currentStep];
            var fields = qsa(visibleStep, 'input[required], select[required], textarea[required]');
            for (var i = 0; i < fields.length; i += 1) {
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    return false;
                }
            }
            if (!validateAdminPasswordConfirmation()) {
                return false;
            }
            return true;
        }

        function collectSetupPayload() {
            var venueName = (document.getElementById('myvh-venue-name') || { value: '' }).value.trim();
            var venueEmail = (document.getElementById('myvh-venue-email') || { value: '' }).value.trim();
            var rooms = [];
            var pricing = [];
            var addons = [];

            qsa(form, '.myvh-room-row').forEach(function (row) {
                var index = row.getAttribute('data-room-index');
                var nameField = row.querySelector('[data-room-field="name"]');
                var capacityField = row.querySelector('[data-room-field="capacity"]');
                var roomName = nameField ? nameField.value.trim() : '';
                if (!roomName) {
                    return;
                }

                var roomKey = 'room-' + index;
                var room = {
                    key: roomKey,
                    name: roomName
                };
                var capacityValue = capacityField ? capacityField.value.trim() : '';
                if (capacityValue !== '') {
                    room.capacity = capacityValue;
                }
                rooms.push(room);

                var rateField = form.querySelector('[data-pricing-field="hourly_rate"][data-room-index="' + index + '"]');
                var rateRaw = rateField ? rateField.value.trim() : '';
                var rate = safeParseFloat(rateRaw);
                if (rate !== null) {
                    pricing.push({
                        room_key: roomKey,
                        hourly_rate: rate
                    });
                }
            });

            qsa(form, '.myvh-addon-row').forEach(function (row) {
                var nameField = row.querySelector('[data-addon-field="name"]');
                var priceField = row.querySelector('[data-addon-field="price"]');
                var name = nameField ? nameField.value.trim() : '';
                var priceRaw = priceField ? priceField.value.trim() : '';
                var hasContent = name !== '' || priceRaw !== '';
                if (!hasContent) {
                    return;
                }

                var addon = {};
                if (name !== '') {
                    addon.name = name;
                }
                if (priceRaw !== '') {
                    addon.price = priceRaw;
                }
                addons.push(addon);
            });

            return {
                venue: {
                    name: venueName,
                    email: venueEmail
                },
                rooms: rooms,
                pricing: pricing,
                addons: addons
            };
        }

        function hydrateFromDraft() {
            var draftNode = document.getElementById('myvh-setup-draft-data');
            if (!draftNode || !draftNode.textContent) {
                return;
            }

            var draft = {};
            try {
                draft = JSON.parse(draftNode.textContent);
            } catch (err) {
                return;
            }

            if (!draft || typeof draft !== 'object') {
                return;
            }

            if (draft.venue && typeof draft.venue === 'object') {
                var venueNameInput = document.getElementById('myvh-venue-name');
                var venueEmailInput = document.getElementById('myvh-venue-email');
                if (venueNameInput && draft.venue.name) {
                    venueNameInput.value = String(draft.venue.name);
                }
                if (venueEmailInput && (draft.venue.email || draft.venue.contact_email)) {
                    venueEmailInput.value = String(draft.venue.email || draft.venue.contact_email);
                }
            }

            var rooms = Array.isArray(draft.rooms) ? draft.rooms : [];
            rooms.slice(0, 3).forEach(function (room, idx) {
                var roomIndex = String(idx + 1);
                var nameField = form.querySelector('[data-room-field="name"][data-room-index="' + roomIndex + '"]');
                var capacityField = form.querySelector('[data-room-field="capacity"][data-room-index="' + roomIndex + '"]');
                if (nameField && room.name) {
                    nameField.value = String(room.name);
                }
                if (capacityField && typeof room.capacity !== 'undefined') {
                    capacityField.value = String(room.capacity);
                }

                var matchingRate = null;
                if (Array.isArray(draft.pricing)) {
                    matchingRate = draft.pricing.find(function (row) {
                        if (!row || typeof row !== 'object') {
                            return false;
                        }
                        if (room.key && row.room_key && String(row.room_key) === String(room.key)) {
                            return true;
                        }
                        return false;
                    }) || draft.pricing[idx] || null;
                }

                var rateField = form.querySelector('[data-pricing-field="hourly_rate"][data-room-index="' + roomIndex + '"]');
                if (rateField && matchingRate && typeof matchingRate.hourly_rate !== 'undefined') {
                    rateField.value = String(matchingRate.hourly_rate);
                }
            });

            var addons = Array.isArray(draft.addons) ? draft.addons : [];
            addons.slice(0, 3).forEach(function (addon, idx) {
                var addonIndex = String(idx + 1);
                var addonName = form.querySelector('[data-addon-field="name"][data-addon-index="' + addonIndex + '"]');
                var addonPrice = form.querySelector('[data-addon-field="price"][data-addon-index="' + addonIndex + '"]');
                if (addonName && addon && addon.name) {
                    addonName.value = String(addon.name);
                }
                if (addonPrice && addon && typeof addon.price !== 'undefined') {
                    addonPrice.value = String(addon.price);
                }
            });

            if (setupPayloadInput) {
                setupPayloadInput.value = JSON.stringify(collectSetupPayload());
            }
        }

        function validateSetupPayload(payload) {
            if (!payload.venue.name) {
                alert('Venue name is required.');
                setStep(1);
                return false;
            }

            if (!payload.venue.email) {
                alert('Venue email is required.');
                setStep(1);
                return false;
            }

            if (payload.rooms.length < 1 || payload.rooms.length > 3) {
                alert('Please provide between 1 and 3 rooms.');
                setStep(2);
                return false;
            }

            for (var i = 0; i < payload.rooms.length; i += 1) {
                if (!payload.rooms[i].name) {
                    alert('Each room needs a name.');
                    setStep(2);
                    return false;
                }
            }

            var pricedRoomKeys = {};
            for (var j = 0; j < payload.pricing.length; j += 1) {
                var row = payload.pricing[j];
                if (!row.hourly_rate || row.hourly_rate <= 0) {
                    alert('Hourly rates must be greater than zero.');
                    setStep(3);
                    return false;
                }
                pricedRoomKeys[row.room_key] = true;
            }

            for (var k = 0; k < payload.rooms.length; k += 1) {
                if (!pricedRoomKeys[payload.rooms[k].key]) {
                    alert('Every room needs an hourly rate.');
                    setStep(3);
                    return false;
                }
            }

            for (var a = 0; a < payload.addons.length; a += 1) {
                var addon = payload.addons[a];
                if (!addon.name) {
                    alert('Add-on name is required when an add-on is provided.');
                    setStep(4);
                    return false;
                }
                if (typeof addon.price !== 'undefined' && addon.price !== '' && isNaN(Number(addon.price))) {
                    alert('Add-on price must be numeric.');
                    setStep(4);
                    return false;
                }
            }

            return true;
        }

        function renderReview() {
            if (!reviewBox) {
                return;
            }

            var payload = collectSetupPayload();
            var roomLines = payload.rooms.map(function (room) {
                var cap = typeof room.capacity !== 'undefined' ? ' (cap ' + room.capacity + ')' : '';
                return room.name + cap;
            });

            var addonLines = payload.addons.map(function (addon) {
                if (typeof addon.price === 'undefined' || addon.price === '') {
                    return addon.name;
                }
                return addon.name + ' (' + addon.price + ')';
            });

            reviewBox.innerHTML = '' +
                '<h4>Setup summary</h4>' +
                '<p><strong>Venue:</strong> ' + (payload.venue.name || 'Not set') + '</p>' +
                '<p><strong>Venue email:</strong> ' + (payload.venue.email || 'Not set') + '</p>' +
                '<p><strong>Rooms:</strong> ' + (roomLines.length ? roomLines.join(', ') : 'Not set') + '</p>' +
                '<p><strong>Add-ons:</strong> ' + (addonLines.length ? addonLines.join(', ') : 'None') + '</p>';
        }

        function saveDraft() {
            if (!nonceInput || !draftTokenInput) {
                return;
            }

            var params = new URLSearchParams();
            params.set('myvh_save_setup_draft', '1');
            params.set('myvh_create_site_nonce', nonceInput.value || '');
            params.set('myvh_setup_draft_token', draftTokenInput.value || '');
            params.set('site_name', (form.querySelector('[name="site_name"]') || { value: '' }).value || '');
            params.set('subdomain', (form.querySelector('[name="subdomain"]') || { value: '' }).value || '');
            params.set('admin_email', (form.querySelector('[name="admin_email"]') || { value: '' }).value || '');
            params.set('admin_first_name', (form.querySelector('[name="admin_first_name"]') || { value: '' }).value || '');
            params.set('admin_last_name', (form.querySelector('[name="admin_last_name"]') || { value: '' }).value || '');
            params.set('setup_payload', JSON.stringify(collectSetupPayload()));

            fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: params.toString()
            }).catch(function () {
                return null;
            });
        }

        function scheduleDraftSave() {
            if (draftSaveTimer) {
                window.clearTimeout(draftSaveTimer);
            }
            draftSaveTimer = window.setTimeout(saveDraft, 700);
        }

        function syncPricingRoomLabels() {
            qsa(form, '[data-room-title-index]').forEach(function (labelNode) {
                var index = labelNode.getAttribute('data-room-title-index');
                var roomNameField = form.querySelector('[data-room-field="name"][data-room-index="' + index + '"]');
                var value = roomNameField ? roomNameField.value.trim() : '';
                labelNode.textContent = value || ('Room ' + index);
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                setStep(currentStep - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                if (!validateVisibleRequiredFields()) {
                    return;
                }
                setStep(currentStep + 1);
            });
        }

        form.addEventListener('input', function (event) {
            if (event.target && event.target.matches('[data-room-field="name"]')) {
                syncPricingRoomLabels();
            }

            if (event.target && (event.target.matches('[name="admin_password"]') || event.target.matches('[name="admin_password_confirm"]'))) {
                var adminPasswordConfirmInput = form.querySelector('[name="admin_password_confirm"]');
                if (adminPasswordConfirmInput) {
                    adminPasswordConfirmInput.setCustomValidity('');
                }
            }

            scheduleDraftSave();
        });

        form.addEventListener('submit', function (event) {
            if (!validateVisibleRequiredFields()) {
                event.preventDefault();
                return;
            }

            var payload = collectSetupPayload();
            if (!validateSetupPayload(payload)) {
                event.preventDefault();
                return;
            }
            if (setupPayloadInput) {
                setupPayloadInput.value = JSON.stringify(payload);
            }
        });

        hydrateFromDraft();
        syncPricingRoomLabels();
        setStep(0);
    }

    function buildSubdomainPreview(form) {
        var input = document.getElementById('myvh-subdomain-input');
        var preview = document.getElementById('myvh-subdomain-preview-value');
        if (!input || !preview) {
            return;
        }

        var isSubdomain = form.getAttribute('data-is-subdomain') === '1';
        var domain = form.getAttribute('data-network-domain') || '';
        var path = form.getAttribute('data-network-path') || '/';

        function update() {
            var slug = input.value.trim() || 'yoursite';
            preview.textContent = isSubdomain ? (slug + '.' + domain) : (domain + path + slug);
        }

        input.addEventListener('input', update);
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.myvh-site-request-form[data-myvh-wizard="1"]');
        if (!form) {
            return;
        }

        buildSubdomainPreview(form);
        buildWizard(form);
    });
})();
