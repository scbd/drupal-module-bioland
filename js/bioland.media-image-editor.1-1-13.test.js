/**
 * @file
 * Unit tests for bioland-media-image-editor-1-1-13.js
 */

describe('Bioland Media Image Editor toggle', () => {
  function buildForm(collapsed) {
    document.body.innerHTML = `
      <form data-drupal-selector="media-image-edit-form">
        <div class="bioland-image-editor-wrapper">
          <button type="button" class="button bioland-image-editor-toggle"
            aria-expanded="${collapsed ? 'false' : 'true'}" aria-controls="bioland-image-editor"
            data-bioland-image-editor-toggle="true"
            data-label-show="Edit image" data-label-hide="Hide image editor">${collapsed ? 'Edit image' : 'Hide image editor'}</button>
          <fieldset id="bioland-image-editor" class="bioland-image-editor${collapsed ? ' bioland-image-editor--collapsed' : ''}">
            <div id="toast-image-editor"></div>
          </fieldset>
        </div>
      </form>`;
  }

  beforeEach(() => {
    jest.resetModules();
    global.once = (id, selector, context) => {
      const root = context || document;
      return Array.from(root.querySelectorAll(selector)).filter((el) => {
        if (el.hasAttribute(`data-once-${id}`)) {
          return false;
        }
        el.setAttribute(`data-once-${id}`, 'true');
        return true;
      });
    };
    require('./bioland-media-image-editor-1-1-13.js');
  });

  afterEach(() => {
    document.body.innerHTML = '';
    delete Drupal.behaviors.biolandMediaImageEditor;
  });

  test('expands a collapsed editor and relabels the button', () => {
    buildForm(true);
    const resize = jest.fn();
    window.addEventListener('resize', resize);
    Drupal.behaviors.biolandMediaImageEditor.attach(document);

    document.querySelector('[data-bioland-image-editor-toggle]').click();

    const editor = document.getElementById('bioland-image-editor');
    const button = document.querySelector('[data-bioland-image-editor-toggle]');
    expect(editor.classList.contains('bioland-image-editor--collapsed')).toBe(false);
    expect(button.getAttribute('aria-expanded')).toBe('true');
    expect(button.textContent).toBe('Hide image editor');
    expect(resize).toHaveBeenCalledTimes(1);
  });

  test('collapses an open editor again', () => {
    buildForm(false);
    Drupal.behaviors.biolandMediaImageEditor.attach(document);

    document.querySelector('[data-bioland-image-editor-toggle]').click();

    const editor = document.getElementById('bioland-image-editor');
    const button = document.querySelector('[data-bioland-image-editor-toggle]');
    expect(editor.classList.contains('bioland-image-editor--collapsed')).toBe(true);
    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(button.textContent).toBe('Edit image');
  });

  test('never submits the form and binds once per button', () => {
    buildForm(true);
    const submit = jest.fn((e) => e.preventDefault());
    document.querySelector('form').addEventListener('submit', submit);
    Drupal.behaviors.biolandMediaImageEditor.attach(document);
    Drupal.behaviors.biolandMediaImageEditor.attach(document);

    document.querySelector('[data-bioland-image-editor-toggle]').click();

    // A second bound handler would toggle straight back to collapsed.
    expect(document.getElementById('bioland-image-editor').classList.contains('bioland-image-editor--collapsed')).toBe(false);
    expect(submit).not.toHaveBeenCalled();
  });

  test('ignores a toggle whose editor is missing', () => {
    buildForm(true);
    document.getElementById('bioland-image-editor').remove();
    expect(() => Drupal.behaviors.biolandMediaImageEditor.attach(document)).not.toThrow();
    expect(() => document.querySelector('[data-bioland-image-editor-toggle]').click()).not.toThrow();
  });
});
