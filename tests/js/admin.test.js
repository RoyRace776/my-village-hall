const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/admin.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function createJQueryStub(win) {
  class JQueryWrapper {
    constructor(elements) {
      this.elements = elements;
      this.length = elements.length;
    }

    first() {
      return this.elements[0] || null;
    }

    on(eventName, handler) {
      this.elements.forEach((el) => {
        el.addEventListener(eventName, function(event) {
          handler.call(el, event);
        });
      });
      return this;
    }

    off() {
      return this;
    }

    is(selector) {
      const el = this.first();
      if (!el) {
        return false;
      }

      if (selector === ':checked') {
        return !!el.checked;
      }

      return false;
    }

    show() {
      this.elements.forEach((el) => {
        el.style.display = '';
      });
      return this;
    }

    hide() {
      this.elements.forEach((el) => {
        el.style.display = 'none';
      });
      return this;
    }

    slideDown() {
      return this.show();
    }

    slideUp() {
      return this.hide();
    }

    fadeIn() {
      return this.show();
    }

    fadeOut() {
      return this.hide();
    }

    removeClass(className) {
      this.elements.forEach((el) => el.classList.remove(...className.split(' ').filter(Boolean)));
      return this;
    }

    addClass(className) {
      this.elements.forEach((el) => el.classList.add(...className.split(' ').filter(Boolean)));
      return this;
    }

    text(value) {
      if (value === undefined) {
        return this.first() ? this.first().textContent : '';
      }

      this.elements.forEach((el) => {
        el.textContent = value;
      });
      return this;
    }

    html(value) {
      if (value === undefined) {
        return this.first() ? this.first().innerHTML : '';
      }

      this.elements.forEach((el) => {
        el.innerHTML = value;
      });
      return this;
    }

    val(value) {
      if (value === undefined) {
        const el = this.first();
        return el ? el.value : '';
      }

      this.elements.forEach((el) => {
        el.value = value;
      });
      return this;
    }

    prop(name, value) {
      if (value === undefined) {
        const el = this.first();
        return el ? el[name] : undefined;
      }

      this.elements.forEach((el) => {
        el[name] = value;
      });
      return this;
    }

    after(html) {
      this.elements.forEach((el) => el.insertAdjacentHTML('afterend', html));
      return this;
    }

    remove() {
      this.elements.forEach((el) => el.remove());
      return this;
    }

    closest(selector) {
      const found = this.elements
        .map((el) => el.closest(selector))
        .filter(Boolean);
      return new JQueryWrapper(found);
    }

    find(selector) {
      const found = [];
      this.elements.forEach((el) => {
        found.push(...Array.from(el.querySelectorAll(selector)));
      });
      return new JQueryWrapper(found);
    }

    data(key) {
      const el = this.first();
      if (!el) {
        return undefined;
      }

      return el.dataset[key];
    }

    serialize() {
      const form = this.first();
      if (!(form instanceof win.HTMLFormElement)) {
        return '';
      }

      const params = new URLSearchParams();
      const formData = new win.FormData(form);
      for (const [key, value] of formData.entries()) {
        params.append(key, value);
      }
      return params.toString();
    }
  }

  function $(input) {
    if (input === win.document) {
      return {
        ready(callback) {
          callback($);
        }
      };
    }

    if (input instanceof win.Element || input === win || input === win.document) {
      return new JQueryWrapper([input]);
    }

    if (typeof input === 'string') {
      return new JQueryWrapper(Array.from(win.document.querySelectorAll(input)));
    }

    if (typeof input === 'function') {
      input($);
      return new JQueryWrapper([]);
    }

    return new JQueryWrapper([]);
  }

  $.ajax = jest.fn();

  return $;
}

describe('admin.js behaviors', () => {
  beforeEach(() => {
    jest.restoreAllMocks();

    document.body.innerHTML = `
      <input type="checkbox" class="myvh-org-invoice-toggle">
      <div class="myvh-org-billing-fields">Billing</div>

      <input type="checkbox" id="enable_recurring">
      <div id="recurring_options" style="display:none">Recurring section</div>

      <select id="recurrence_type">
        <option value="daily">Daily</option>
        <option value="monthly_day">Monthly day</option>
      </select>
      <div id="recurrence_interval_row" style="display:none"></div>
      <span id="interval_unit"></span>
      <div id="monthly_day_options" style="display:none"></div>

      <select id="venue_id">
        <option value="">Select...</option>
        <option value="22">Venue 22</option>
      </select>
      <div id="room-checkboxes"></div>
    `;

    window.myvhAjax = {
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'nonce-1'
    };

    const $ = createJQueryStub(window);
    window.jQuery = $;
    global.jQuery = $;

    window.eval(scriptSource);
  });

  test('hides billing fields by default and shows them when toggle is checked', () => {
    const toggle = document.querySelector('.myvh-org-invoice-toggle');
    const billingFields = document.querySelector('.myvh-org-billing-fields');

    expect(billingFields.style.display).toBe('none');

    toggle.checked = true;
    toggle.dispatchEvent(new Event('change', { bubbles: true }));

    expect(billingFields.style.display).toBe('');
  });

  test('toggles recurring options visibility when enable_recurring changes', () => {
    const recurringToggle = document.getElementById('enable_recurring');
    const recurringOptions = document.getElementById('recurring_options');

    recurringToggle.checked = true;
    recurringToggle.dispatchEvent(new Event('change', { bubbles: true }));
    expect(recurringOptions.style.display).toBe('');

    recurringToggle.checked = false;
    recurringToggle.dispatchEvent(new Event('change', { bubbles: true }));
    expect(recurringOptions.style.display).toBe('none');
  });

  test('updates recurrence interval and monthly options by recurrence type', () => {
    const recurrenceType = document.getElementById('recurrence_type');
    const intervalRow = document.getElementById('recurrence_interval_row');
    const intervalUnit = document.getElementById('interval_unit');
    const monthlyOptions = document.getElementById('monthly_day_options');

    recurrenceType.value = 'daily';
    recurrenceType.dispatchEvent(new Event('change', { bubbles: true }));

    expect(intervalRow.style.display).toBe('');
    expect(intervalUnit.textContent).toBe('days');
    expect(monthlyOptions.style.display).toBe('none');

    recurrenceType.value = 'monthly_day';
    recurrenceType.dispatchEvent(new Event('change', { bubbles: true }));

    expect(intervalRow.style.display).toBe('none');
    expect(monthlyOptions.style.display).toBe('');
  });

  test('requests room list when venue changes', () => {
    const venueSelect = document.getElementById('venue_id');

    venueSelect.value = '22';
    venueSelect.dispatchEvent(new Event('change', { bubbles: true }));

    expect(window.jQuery.ajax).toHaveBeenCalledTimes(1);
    const ajaxConfig = window.jQuery.ajax.mock.calls[0][0];
    expect(ajaxConfig.type).toBe('POST');
    expect(ajaxConfig.url).toBe('/wp-admin/admin-ajax.php');
    expect(ajaxConfig.data.action).toBe('myvh_get_rooms_by_venue');
    expect(ajaxConfig.data.venue_id).toBe('22');
    expect(ajaxConfig.data.nonce).toBe('nonce-1');
  });
});
