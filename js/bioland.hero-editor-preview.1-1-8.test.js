/**
 * @file
 * Unit tests for bioland-hero-editor-preview-1-1-8.js
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

    document.body.innerHTML = `
      <form id="media-hero-add-form">
        ${unrelatedEditable}
        <div data-drupal-selector="edit-field-media-image-0">
          ${image}
          ${originalLink}
          ${focalPoint}
        </div>
        <div data-drupal-selector="edit-field-description-0">
          ${descriptionEditable}
        </div>
      </form>
    `;
    return document.getElementById('media-hero-add-form');
  }

  function widget() {
    return document.querySelector('[data-drupal-selector*="edit-field-media-image"]');
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
    require('./bioland-hero-editor-preview-1-1-8.js');
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
    widget().outerHTML = '<div data-drupal-selector="edit-field-media-image-0"></div>';
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(wrapper().style.getPropertyValue('--bioland-hero-primary')).toBe('');
    expect(wrapper().style.getPropertyValue('--bioland-hero-secondary')).toBe('');
  });

  test('clears the preview after the image is removed and the widget re-renders', () => {
    buildForm();
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    expect(wrapper().classList.contains('bioland-hero-preview')).toBe(true);

    // Simulate the widget's AJAX re-render: a fresh wrapper node with no image.
    widget().outerHTML = '<div data-drupal-selector="edit-field-media-image-0"></div>';
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
    focalInput.dispatchEvent(new Event('change'));

    expect(wrapper().style.backgroundPosition).toBe('10% 20%');
  });

  test('runs once per widget element across repeated attach calls', () => {
    buildForm({ withFocalPoint: true });
    const focalInput = widget().querySelector('.focal-point');
    const addSpy = jest.spyOn(focalInput, 'addEventListener');

    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);
    Drupal.behaviors.biolandHeroEditorPreview.attach(document, drupalSettings);

    expect(addSpy).toHaveBeenCalledTimes(1);
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
