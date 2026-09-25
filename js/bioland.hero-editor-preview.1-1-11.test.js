/**
 * @file
 * Unit tests for bioland-hero-editor-preview-1-1-11.js
 */

describe('Bioland Hero Editor Preview', () => {
  /**
   * Builds a media_hero form DOM: image widget + CKEditor 5 editable.
   *
   * @param {Object} options
   *   `{ withImage, withOriginalLink, withFocalPoint, withDescriptionEditable,
   *   withUnrelatedEditable }`.
   */
  function buildForm(options) {
    const opts = options || {};
    const image = opts.withImage !== false
      ? '<img class="image-preview" src="/files/styles/thumbnail/hero.jpg">'
      : '';
    const originalLink = opts.withOriginalLink
      ? '<span class="file-link"><a href="/files/hero-original.jpg" type="original">hero.jpg</a></span>'
      : '';
    const focalPoint = opts.withFocalPoint
      ? '<input class="focal-point" name="field_media_image[0][focal_point]" value="30,70">'
      : '';
    const descriptionEditable = opts.withDescriptionEditable !== false
      ? '<div class="ck-editor__editable"><div class="ck-content"><p>Hero text</p></div></div>'
      : '';
    // An unrelated CKEditor instance elsewhere in the form (e.g. a caption
    // field) that the preview must never touch.
    const unrelatedEditable = opts.withUnrelatedEditable
      ? '<div data-drupal-selector="edit-field-caption-0"><div class="ck-editor__editable"><div class="ck-content"><p>Caption</p></div></div></div>'
      : '';

    // Mirrors the real widget: the outer field wrapper survives AJAX, the
    // inner #ajax-wrapper is what upload/remove swaps out, and Drupal stamps
    // several descendants with their own "edit-field-media-image-0-*"
    // selectors (none of which hold an image of their own).
    document.body.innerHTML = `
      <form id="media-hero-add-form">
        ${unrelatedEditable}
        <div data-drupal-selector="edit-field-media-image-wrapper">
          <div id="ajax-wrapper">
            ${image}
            ${originalLink}
            ${focalPoint}
            <input data-drupal-selector="edit-field-media-image-0-alt" value="Alt">
            <input data-drupal-selector="edit-field-media-image-0-remove-button" type="submit">
            <div data-drupal-selector="edit-field-media-image-0-preview-indicator" class="focal-point-indicator"></div>
          </div>
        </div>
        <div data-drupal-selector="edit-field-description-0">
          ${descriptionEditable}
        </div>
      </form>
    `;
    return document.getElementById('media-hero-add-form');
  }

  function widget() {
    return document.querySelector('[data-drupal-selector="edit-field-media-image-wrapper"]');
  }

  function ajaxWrapper() {
    return widget().querySelector('#ajax-wrapper');
  }

  function descriptionWrapper() {
    return document.querySelector('[data-drupal-selector*="edit-field-description"]');
  }

  function wrapper() {
    return descriptionWrapper().querySelector('.ck-editor__editable');
  }

  function unrelatedWrapper() {
    return document.querySelector('[data-drupal-selector*="edit-field-caption"] .ck-editor__editable');
  }

  beforeEach(() => {
    global.Drupal.behaviors = {};
    global.drupalSettings.bioland.heroPreview = { primary: '#009edb', secondary: '#16c56e' };
    // core/once stand-in: mark-and-filter, like the real library.
    global.once = (id, selector, context) => {
      const attribute = 'data-once-' + id;
      return Array.from((context || document).querySelectorAll(selector))
        .filter((element) => !element.hasAttribute(attribute))
        .map((element) => {
          element.setAttribute(attribute, '');
          return element;
        });
    };
    jest.resetModules();
    require('./bioland-hero-editor-preview-1-1-11.js');
  });

  test('registers the behavior', () => {
    expect(typeof Drupal.behaviors.biolandHeroEditorPreview.attach).toBe('function');
  });

  test('applies the hero background and colours when an image is present', () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);
    expect(wrapper().style.backgroundImage).toContain('hero.jpg');
    expect(wrapper().style.getPropertyValue('--bioland-hero-primary')).toBe('#009edb');
    expect(wrapper().style.getPropertyValue('--bioland-hero-secondary')).toBe('#16c56e');
  });

  test('prefers the original file link over the thumbnail preview', () => {
    buildForm({ withOriginalLink: true });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().style.backgroundImage).toContain('hero-original.jpg');
  });

  test('clears the preview when no image is present', () => {
    buildForm({ withImage: false });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(false);
    expect(wrapper().style.backgroundImage).toBe('');
  });

  test('clears the custom colour properties when the image is removed', () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().style.getPropertyValue('--bioland-hero-primary')).toBe('#009edb');

    // Simulate the widget's AJAX re-render: a fresh wrapper node with no image.
    widget().outerHTML = '<div data-drupal-selector="edit-field-media-image-wrapper"></div>';
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().style.getPropertyValue('--bioland-hero-primary')).toBe('');
    expect(wrapper().style.getPropertyValue('--bioland-hero-secondary')).toBe('');
  });

  test('clears the preview after the image is removed and the widget re-renders', () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);

    // Simulate the widget's AJAX re-render: a fresh wrapper node with no image.
    widget().outerHTML = '<div data-drupal-selector="edit-field-media-image-wrapper"></div>';
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(false);
  });

  test('positions the background using the focal point value', () => {
    buildForm({ withFocalPoint: true });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().style.backgroundPosition).toBe('30% 70%');
  });

  test('centers the background when no focal point is set', () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().style.backgroundPosition).toBe('50% 50%');
  });

  test('updates the preview when the focal point input changes', () => {
    buildForm({ withFocalPoint: true });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().style.backgroundPosition).toBe('30% 70%');

    const focalInput = widget().querySelector('.focal-point');
    focalInput.value = '10,20';
    focalInput.dispatchEvent(new Event('change', { bubbles: true }));

    expect(wrapper().style.backgroundPosition).toBe('10% 20%');
  });

  test('runs once per widget element across repeated attach calls', () => {
    buildForm({ withFocalPoint: true });
    const addSpy = jest.spyOn(widget(), 'addEventListener');

    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(addSpy).toHaveBeenCalledTimes(1);
  });

  test('keeps the preview despite nested descendants that share the widget selector prefix', () => {
    // Regression: a substring selector matched the alt input, remove button and
    // focal indicator too; each found no image and stripped the preview again.
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(document.querySelectorAll('[data-once-bioland-hero-editor-preview]').length).toBe(1);
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);
    expect(wrapper().style.backgroundImage).toContain('hero.jpg');
  });

  test('re-syncs when only the inner ajax-wrapper is swapped by Drupal AJAX', async () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);

    // Image removed: Drupal replaces #ajax-wrapper, the outer wrapper stays.
    ajaxWrapper().outerHTML = '<div id="ajax-wrapper"><input type="file"></div>';
    await Promise.resolve();
    await Promise.resolve();
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(false);

    // New image uploaded / picked from the media library.
    ajaxWrapper().outerHTML = '<div id="ajax-wrapper"><img src="/files/styles/thumbnail/new.jpg"></div>';
    await Promise.resolve();
    await Promise.resolve();
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);
    expect(wrapper().style.backgroundImage).toContain('new.jpg');
  });

  test('follows a focal point set via jQuery (no native change event)', async () => {
    buildForm({ withFocalPoint: true });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().style.backgroundPosition).toBe('30% 70%');

    // focal_point.js writes the value with $.val() and moves its indicator by
    // rewriting the style attribute; no native change event is dispatched.
    widget().querySelector('.focal-point').value = '80,15';
    widget().querySelector('.focal-point-indicator').style.left = '80%';
    await Promise.resolve();
    await Promise.resolve();

    expect(wrapper().style.backgroundPosition).toBe('80% 15%');
  });

  test('scopes the editable lookup to the field_description wrapper', () => {
    buildForm({ withUnrelatedEditable: true });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);
    expect(unrelatedWrapper().classList.contains('bioland-hero-preview')).toBe(false);
  });

  test('skips the preview entirely when drupalSettings.bioland.heroPreview is missing', () => {
    buildForm();
    delete drupalSettings.bioland.heroPreview;

    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(false);
    expect(wrapper().style.backgroundImage).toBe('');
  });

  test('renders once the CKEditor 5 editable mounts asynchronously after attach()', async () => {
    buildForm({ withDescriptionEditable: false });
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    // Not mounted yet: nothing to apply the preview to.
    expect(descriptionWrapper().querySelector('.ck-content')).toBeNull();

    // Simulate CKEditor 5 finishing its async mount.
    descriptionWrapper().innerHTML = '<div class="ck-editor__editable"><div class="ck-content"><p>Hero text</p></div></div>';

    // MutationObserver callbacks run as a microtask; flush it.
    await Promise.resolve();
    await Promise.resolve();

    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);
    expect(wrapper().style.backgroundImage).toContain('hero.jpg');
  });
});
