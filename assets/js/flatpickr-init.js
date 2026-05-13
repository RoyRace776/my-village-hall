(function(window) {
    "use strict";

    function ensureArray(value) {
        if (!value) {
            return [];
        }

        return Array.isArray(value) ? value.slice() : [value];
    }

    function settingsDirtyHook(input, selectedDates, dateStr, instance) {
        if (typeof window.MyvhMarkDirty === "function") {
            window.MyvhMarkDirty("flatpickr:shared");
        }

        const form = document.querySelector(".myvh-settings-form");
        if (form && form.contains(input)) {
            const submit = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submit) {
                submit.disabled = false;
                submit.removeAttribute("disabled");
                submit.setAttribute("aria-disabled", "false");
                if (submit.classList) {
                    submit.classList.remove("disabled");
                    submit.classList.remove("button-disabled");
                }
            }
        }

        if (instance && instance.altInput) {
            instance.altInput.dispatchEvent(new CustomEvent("myvh:flatpickr-change", {
                bubbles: true,
                detail: { value: dateStr || "", selectedDates: selectedDates || [] }
            }));
        }

        input.dispatchEvent(new CustomEvent("myvh:flatpickr-change", {
            bubbles: true,
            detail: { value: dateStr || "", selectedDates: selectedDates || [] }
        }));
    }

    function resolveFormat(input, mode) {
        if (input.dataset.myvhFormat) {
            return input.dataset.myvhFormat;
        }

        return mode === "time" ? "H:i" : "Y-m-d";
    }

    function getAttributeValue(input, key) {
        const dataKey = key.replace(/^data-/, "").replace(/-([a-z])/g, function(_, char) {
            return char.toUpperCase();
        });

        if (input.dataset[dataKey]) {
            return input.dataset[dataKey];
        }

        return input.getAttribute(key) || "";
    }

    function buildOptions(input, options) {
        const mode = input.dataset.myvhPicker || "date";
        const dateFormat = resolveFormat(input, mode);
        const minDate = getAttributeValue(input, "data-min-date") || input.getAttribute("min") || undefined;
        const maxDate = getAttributeValue(input, "data-max-date") || input.getAttribute("max") || undefined;
        const firstDayOfWeek = parseInt(getAttributeValue(input, "data-myvh-first-day") || "1", 10);
        const providedOnChange = ensureArray(options && options.onChange);
        const providedOnValueUpdate = ensureArray(options && options.onValueUpdate);
        const dirtyHook = function(selectedDates, dateStr, instance) {
            settingsDirtyHook(input, selectedDates, dateStr, instance);
        };
        const baseOptions = {
            allowInput: input.dataset.myvhAllowInput !== "0",
            clickOpens: !input.disabled,
            dateFormat: dateFormat,
            disableMobile: true,
            minDate: minDate,
            maxDate: maxDate,
            ...options,
            onChange: [...providedOnChange, dirtyHook],
            onValueUpdate: [...providedOnValueUpdate, dirtyHook]
        };

        if (mode === "time") {
            baseOptions.enableTime = true;
            baseOptions.noCalendar = true;
            baseOptions.time_24hr = true;
            baseOptions.minuteIncrement = parseInt(input.dataset.myvhMinuteIncrement || "15", 10);
        } else {
            baseOptions.altInput = input.dataset.myvhAltInput !== "0";
            baseOptions.altFormat = input.dataset.myvhAltFormat || "d/m/Y";
            baseOptions.altInputClass = input.className + " flatpickr-alt-input";
            baseOptions.locale = {
                firstDayOfWeek: Number.isNaN(firstDayOfWeek) ? 1 : firstDayOfWeek
            };
        }

        return baseOptions;
    }

    function initInput(input, options) {
        if (!input || typeof window.flatpickr !== "function") {
            return null;
        }

        const config = buildOptions(input, options || {});
        const instance = input._flatpickr || window.flatpickr(input, config);

        if (instance && typeof instance.set === "function") {
            instance.set(config);
        }

        setValue(input, input.value || "");
        syncState(input);

        return instance;
    }

    function initWithin(root, optionsBySelector) {
        const container = root || document;
        const inputs = container.querySelectorAll("[data-myvh-picker]");

        inputs.forEach(function(input) {
            let options = {};

            if (optionsBySelector) {
                Object.keys(optionsBySelector).some(function(selector) {
                    if (input.matches(selector)) {
                        options = optionsBySelector[selector] || {};
                        return true;
                    }

                    return false;
                });
            }

            initInput(input, options);
        });
    }

    function setValue(input, value) {
        if (!input) {
            return;
        }

        const nextValue = String(value || "");
        const mode = input.dataset.myvhPicker || "date";
        const format = resolveFormat(input, mode);

        if (input._flatpickr && typeof input._flatpickr.setDate === "function") {
            input._flatpickr.setDate(nextValue, false, format);
        } else {
            input.value = nextValue;
        }
    }

    function syncState(input) {
        if (!input || !input._flatpickr || typeof input._flatpickr.set !== "function") {
            return;
        }

        input._flatpickr.set("clickOpens", !input.disabled);

        if (input._flatpickr.altInput) {
            input._flatpickr.altInput.disabled = input.disabled;
            input._flatpickr.altInput.readOnly = input.dataset.myvhAllowInput === "0";
        }

        if (input.disabled && typeof input._flatpickr.close === "function") {
            input._flatpickr.close();
        }
    }

    function syncWithin(root) {
        const container = root || document;

        container.querySelectorAll("[data-myvh-picker]").forEach(function(input) {
            setValue(input, input.value || "");
            syncState(input);
        });
    }

    window.MyvhFlatpickr = {
        initInput: initInput,
        initWithin: initWithin,
        setValue: setValue,
        syncState: syncState,
        syncWithin: syncWithin
    };
})(window);