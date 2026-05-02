const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/portal-email.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('MyvhPortalEmail', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.MyvhPortalEmail;
    delete window.tinymce;

    window.MyvhPortalAjax = {
      post: jest.fn().mockResolvedValue({ success: true, data: { message: 'OK', subject: 'Preview', html: '<p>Body</p>' } })
    };
    window.MyvhPortalDialog = {
      confirm: jest.fn().mockResolvedValue(true)
    };

    window.eval(scriptSource);
  });

  test('resets template from templates page after confirmation', async () => {
    document.body.innerHTML = `
      <div class="myvh-email-templates-page">
        <div id="msg"></div>
        <form data-email-template-reset="1" data-template-slug="welcome" data-confirm="Confirm reset" data-message-target="msg">
          <button type="submit">Reset</button>
        </form>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplatesPage();

    const form = document.querySelector('form[data-email-template-reset="1"]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await flushPromises();
    await flushPromises();

    expect(window.MyvhPortalDialog.confirm).toHaveBeenCalledWith('Confirm reset');
    expect(window.MyvhPortalAjax.post).toHaveBeenCalledWith('myvh_reset_email_template', { template: 'welcome' }, { scope: 'portal' });
    expect(window.location.hash).toContain('#email-templates?refresh=');
  });

  test('inserts placeholder token into textarea when tinymce is unavailable', () => {
    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="Hello" />
          <textarea id="myvh-email-template-body">Hi </textarea>
        </form>
        <div id="myvh-email-template-message"></div>
        <button data-email-placeholder="{{customer_name}}">Insert</button>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    const textarea = document.getElementById('myvh-email-template-body');
    textarea.focus();
    textarea.setSelectionRange(3, 3);

    document.querySelector('[data-email-placeholder]').click();

    expect(textarea.value).toBe('Hi {{customer_name}}');
  });

  test('validates required subject and body before save', async () => {
    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="" />
          <textarea id="myvh-email-template-body"></textarea>
        </form>
        <div id="myvh-email-template-message"></div>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    document.getElementById('myvh-email-template-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    expect(window.MyvhPortalAjax.post).not.toHaveBeenCalled();
    expect(document.getElementById('myvh-email-template-message').textContent).toBe('Subject and body are required');
  });

  test('builds preview and opens preview modal', async () => {
    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="Preview Subject" />
          <textarea id="myvh-email-template-body"><p>Body</p></textarea>
        </form>
        <div id="myvh-email-template-message"></div>
        <button data-email-template-preview="1">Preview</button>
        <div id="myvh-email-template-preview-modal" hidden>
          <div data-email-preview-content></div>
        </div>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    document.querySelector('[data-email-template-preview="1"]').click();
    await flushPromises();
    await flushPromises();

    expect(window.MyvhPortalAjax.post).toHaveBeenCalledWith(
      'myvh_preview_email_template',
      { template: 'welcome', subject: 'Preview Subject', html_body: '<p>Body</p>' },
      { scope: 'portal' }
    );

    expect(document.getElementById('myvh-email-template-preview-modal').hidden).toBe(false);
    expect(document.querySelector('[data-email-preview-content]').innerHTML).toContain('<h4>Preview</h4>');
  });

  test('prevents duplicate rapid save submits while saving is in progress', async () => {
    let resolveSave;
    window.MyvhPortalAjax.post.mockReturnValueOnce(new Promise((resolve) => {
      resolveSave = resolve;
    }));

    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="Save Subject" />
          <textarea id="myvh-email-template-body"><p>Body</p></textarea>
        </form>
        <div id="myvh-email-template-message"></div>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    const form = document.getElementById('myvh-email-template-form');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(window.MyvhPortalAjax.post).toHaveBeenCalledTimes(1);

    resolveSave({ success: true, data: { message: 'Saved' } });
    await flushPromises();
    await flushPromises();
  });

  test('prevents duplicate rapid preview clicks while preview is in progress', async () => {
    let resolvePreview;
    window.MyvhPortalAjax.post.mockReturnValueOnce(new Promise((resolve) => {
      resolvePreview = resolve;
    }));

    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="Preview Subject" />
          <textarea id="myvh-email-template-body"><p>Body</p></textarea>
        </form>
        <div id="myvh-email-template-message"></div>
        <button data-email-template-preview="1">Preview</button>
        <div id="myvh-email-template-preview-modal" hidden>
          <div data-email-preview-content></div>
        </div>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    const previewBtn = document.querySelector('[data-email-template-preview="1"]');
    previewBtn.click();
    previewBtn.click();

    expect(window.MyvhPortalAjax.post).toHaveBeenCalledTimes(1);

    resolvePreview({ success: true, data: { subject: 'Preview', html: '<p>Body</p>' } });
    await flushPromises();
    await flushPromises();
  });

  test('prevents duplicate rapid send-test clicks while request is in progress', async () => {
    let resolveSendTest;
    window.MyvhPortalAjax.post.mockReturnValueOnce(new Promise((resolve) => {
      resolveSendTest = resolve;
    }));

    document.body.innerHTML = `
      <div class="myvh-email-template-edit-page" data-template-slug="welcome">
        <form id="myvh-email-template-form">
          <input name="template" value="welcome" />
          <input name="subject" value="Send Subject" />
          <textarea id="myvh-email-template-body"><p>Body</p></textarea>
        </form>
        <div id="myvh-email-template-message"></div>
        <button data-email-template-send-test="1">Send test</button>
      </div>
    `;

    window.MyvhPortalEmail.initEmailTemplateEditPage();

    const sendTestBtn = document.querySelector('[data-email-template-send-test="1"]');
    sendTestBtn.click();
    sendTestBtn.click();

    expect(window.MyvhPortalAjax.post).toHaveBeenCalledTimes(1);
    expect(sendTestBtn.disabled).toBe(true);

    resolveSendTest({ success: true, data: { message: 'Sent' } });
    await flushPromises();
    await flushPromises();
    expect(sendTestBtn.disabled).toBe(false);
  });
});
