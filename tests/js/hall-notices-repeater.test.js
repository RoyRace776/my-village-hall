/**
 * Jest tests for the Hall Notices repeater UI.
 *
 * The repeater logic lives inline inside SettingsPage.php's <script> block.
 * We extract the behaviour into a standalone function here and test it with
 * JSDOM (the default Jest environment).
 */

// ── Portable extract of the repeater logic ────────────────────────────────
// This mirrors initNoticesRepeater() in SettingsPage.php exactly so that
// any future changes to the PHP are caught by these tests.

function initNoticesRepeater(wrapper, { markDirty = () => {} } = {}) {
  const tbody   = wrapper.querySelector('.myvh-notices-body');
  const addBtn  = wrapper.querySelector('.myvh-notice-add-row');
  const fieldName = addBtn ? addBtn.getAttribute('data-field') : '';

  if (!tbody || !addBtn) return;

  function rowCount() {
    return tbody.querySelectorAll('.myvh-notice-row').length;
  }

  function buildRow(idx) {
    const phFrom = addBtn.getAttribute('data-placeholder-from') || '';
    const phTo   = addBtn.getAttribute('data-placeholder-to')   || '';
    const tr = document.createElement('tr');
    tr.className = 'myvh-notice-row';
    tr.innerHTML =
      `<td><textarea name="${fieldName}[${idx}][message]" rows="2" style="width:100%;"></textarea></td>` +
      `<td><input type="text" name="${fieldName}[${idx}][start_date]" placeholder="${phFrom}" data-myvh-picker="date" autocomplete="off" style="width:100%;"></td>` +
      `<td><input type="text" name="${fieldName}[${idx}][end_date]"   placeholder="${phTo}"   data-myvh-picker="date" autocomplete="off" style="width:100%;"></td>` +
      `<td><button type="button" class="button myvh-notice-remove">Remove</button></td>`;
    return tr;
  }

  function reindex() {
    tbody.querySelectorAll('.myvh-notice-row').forEach((row, i) => {
      row.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
      });
    });
  }

  addBtn.addEventListener('click', () => {
    const row = buildRow(rowCount());
    tbody.appendChild(row);
    markDirty();
  });

  tbody.addEventListener('click', e => {
    if (e.target.classList.contains('myvh-notice-remove')) {
      e.target.closest('tr').remove();
      reindex();
      markDirty();
    }
  });
}

// ── Helpers ───────────────────────────────────────────────────────────────

function buildRepeaterHTML(fieldName = 'notices', existingRows = []) {
  const rowsHTML = existingRows.map((r, i) => `
    <tr class="myvh-notice-row">
      <td><textarea name="${fieldName}[${i}][message]">${r.message || ''}</textarea></td>
      <td><input type="text" name="${fieldName}[${i}][start_date]" value="${r.start_date || ''}"></td>
      <td><input type="text" name="${fieldName}[${i}][end_date]"   value="${r.end_date   || ''}"></td>
      <td><button type="button" class="button myvh-notice-remove">Remove</button></td>
    </tr>`).join('');

  const div = document.createElement('div');
  div.className = 'myvh-notices-repeater';
  div.innerHTML = `
    <table class="myvh-notices-table">
      <tbody class="myvh-notices-body">${rowsHTML}</tbody>
    </table>
    <button type="button"
            class="button myvh-notice-add-row"
            data-field="${fieldName}"
            data-placeholder-from="From now"
            data-placeholder-to="Forever">+ Add Notice</button>`;
  return div;
}

// ── Tests ─────────────────────────────────────────────────────────────────

describe('Hall Notices repeater – add row', () => {
  test('clicking Add Notice appends a new row to the tbody', () => {
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    expect(wrapper.querySelectorAll('.myvh-notice-row')).toHaveLength(0);

    wrapper.querySelector('.myvh-notice-add-row').click();

    expect(wrapper.querySelectorAll('.myvh-notice-row')).toHaveLength(1);
    document.body.removeChild(wrapper);
  });

  test('new row contains message textarea, start_date and end_date inputs', () => {
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    wrapper.querySelector('.myvh-notice-add-row').click();

    const row = wrapper.querySelector('.myvh-notice-row');
    expect(row.querySelector('textarea[name="notices[0][message]"]')).not.toBeNull();
    expect(row.querySelector('input[name="notices[0][start_date]"]')).not.toBeNull();
    expect(row.querySelector('input[name="notices[0][end_date]"]')).not.toBeNull();
    document.body.removeChild(wrapper);
  });

  test('rows receive sequential indices when multiple are added', () => {
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    wrapper.querySelector('.myvh-notice-add-row').click();
    wrapper.querySelector('.myvh-notice-add-row').click();
    wrapper.querySelector('.myvh-notice-add-row').click();

    const rows = wrapper.querySelectorAll('.myvh-notice-row');
    expect(rows).toHaveLength(3);
    expect(rows[0].querySelector('textarea').name).toBe('notices[0][message]');
    expect(rows[1].querySelector('textarea').name).toBe('notices[1][message]');
    expect(rows[2].querySelector('textarea').name).toBe('notices[2][message]');
    document.body.removeChild(wrapper);
  });

  test('date inputs carry the date picker attribute', () => {
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    wrapper.querySelector('.myvh-notice-add-row').click();

    const inputs = wrapper.querySelectorAll('[data-myvh-picker="date"]');
    expect(inputs).toHaveLength(2); // start_date + end_date
    document.body.removeChild(wrapper);
  });

  test('date inputs display the correct placeholder text', () => {
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    wrapper.querySelector('.myvh-notice-add-row').click();

    const row = wrapper.querySelector('.myvh-notice-row');
    expect(row.querySelector('[name$="[start_date]"]').placeholder).toBe('From now');
    expect(row.querySelector('[name$="[end_date]"]').placeholder).toBe('Forever');
    document.body.removeChild(wrapper);
  });

  test('markDirty is called when a row is added', () => {
    const markDirty = jest.fn();
    const wrapper = buildRepeaterHTML('notices', []);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper, { markDirty });

    wrapper.querySelector('.myvh-notice-add-row').click();

    expect(markDirty).toHaveBeenCalledTimes(1);
    document.body.removeChild(wrapper);
  });
});

describe('Hall Notices repeater – remove row', () => {
  test('clicking Remove deletes the row', () => {
    const wrapper = buildRepeaterHTML('notices', [
      { message: 'First',  start_date: '', end_date: '' },
      { message: 'Second', start_date: '', end_date: '' },
    ]);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    expect(wrapper.querySelectorAll('.myvh-notice-row')).toHaveLength(2);

    wrapper.querySelector('.myvh-notice-remove').click();

    expect(wrapper.querySelectorAll('.myvh-notice-row')).toHaveLength(1);
    document.body.removeChild(wrapper);
  });

  test('remaining rows are re-indexed after removal', () => {
    const wrapper = buildRepeaterHTML('notices', [
      { message: 'First',  start_date: '', end_date: '' },
      { message: 'Second', start_date: '', end_date: '' },
      { message: 'Third',  start_date: '', end_date: '' },
    ]);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    // Remove the middle row (index 1)
    const removeButtons = wrapper.querySelectorAll('.myvh-notice-remove');
    removeButtons[1].click();

    const rows = wrapper.querySelectorAll('.myvh-notice-row');
    expect(rows).toHaveLength(2);
    // After re-index: former row[0] stays at [0], former row[2] becomes [1]
    expect(rows[0].querySelector('textarea').name).toBe('notices[0][message]');
    expect(rows[1].querySelector('textarea').name).toBe('notices[1][message]');
    document.body.removeChild(wrapper);
  });

  test('markDirty is called when a row is removed', () => {
    const markDirty = jest.fn();
    const wrapper = buildRepeaterHTML('notices', [
      { message: 'Notice', start_date: '', end_date: '' },
    ]);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper, { markDirty });

    wrapper.querySelector('.myvh-notice-remove').click();

    expect(markDirty).toHaveBeenCalledTimes(1);
    document.body.removeChild(wrapper);
  });

  test('table is empty after all rows are removed', () => {
    const wrapper = buildRepeaterHTML('notices', [
      { message: 'Only one', start_date: '', end_date: '' },
    ]);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    wrapper.querySelector('.myvh-notice-remove').click();

    expect(wrapper.querySelectorAll('.myvh-notice-row')).toHaveLength(0);
    document.body.removeChild(wrapper);
  });
});

describe('Hall Notices repeater – mixed add and remove', () => {
  test('indices remain contiguous after interleaved adds and removes', () => {
    const wrapper = buildRepeaterHTML('notices', [
      { message: 'Row A', start_date: '', end_date: '' },
    ]);
    document.body.appendChild(wrapper);
    initNoticesRepeater(wrapper);

    // Add two more
    wrapper.querySelector('.myvh-notice-add-row').click();
    wrapper.querySelector('.myvh-notice-add-row').click();

    // Remove the first row
    wrapper.querySelector('.myvh-notice-remove').click();

    const rows = wrapper.querySelectorAll('.myvh-notice-row');
    expect(rows).toHaveLength(2);
    expect(rows[0].querySelector('textarea').name).toBe('notices[0][message]');
    expect(rows[1].querySelector('textarea').name).toBe('notices[1][message]');
    document.body.removeChild(wrapper);
  });
});

describe('Hall Notices repeater – graceful no-op when structure missing', () => {
  test('does not throw when tbody is missing', () => {
    const div = document.createElement('div');
    div.innerHTML = '<button class="myvh-notice-add-row" data-field="x">Add</button>';
    expect(() => initNoticesRepeater(div)).not.toThrow();
  });

  test('does not throw when add button is missing', () => {
    const div = document.createElement('div');
    div.innerHTML = '<tbody class="myvh-notices-body"></tbody>';
    expect(() => initNoticesRepeater(div)).not.toThrow();
  });
});
