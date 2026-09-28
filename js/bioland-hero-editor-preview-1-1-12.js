/**
 * @file
 * Previews the front-end hero look inside the media hero edit/add form.
 *
 * Once field_media_image has an image, the field_description CKEditor 5
 * editable area is dressed up to match hero-image.vue on the front end: the
 * image as a cover background, the same tint + overlay gradient layers, and
 * white text. Purely cosmetic - the editor content and its saved value are
 * untouched, only the CKEditor wrapper's background/foreground styling.
 *
 * Colours come from drupalSettings.bioland.heroPreview (the site's
 * theme.hero.primary / theme.hero.secondary, with the same BL2/BSL fallback
 * BiolandComponentMenuFormMode::primaryColor() uses - see
 * \Drupal\bioland\Service\BiolandHeroEditorPreview::settings(), called from
 * bioland_hero_editor_preview_settings() in bioland.module). When that
 * settings key is entirely absent this behavior skips the preview rather than
 * guessing a flavour's colours client-side.
 *
 * CKEditor 5 mounts its editable asynchronously, so `.ck-content` may not
 * exist yet when this behavior first attaches (e.g. reopening an edit form
 * that already has a hero image). A MutationObserver on the field_description
 * wrapper retries once the editable appears, then disconnects.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  var WRAPPER_CLASS = 'bioland-hero-preview';
  // The field widget's outer container only. A substring match on
  // "edit-field-media-image" also hits every descendant Drupal stamps with a
  // data-drupal-selector (filename span, remove button, alt/title inputs, the
  // focal point input, the thumbnail itself). Those carry no image of their
  // own, so each one re-ran applyPreview() with an empty URL and stripped the
  // preview the wrapper had just applied - the last match always won.
  var WIDGET_SELECTOR = '[data-drupal-selector="edit-field-media-image-wrapper"]';
  var IMAGE_SELECTOR = 'img';
  // Matches the contrib focal_point 2.x widget markup (input.focal-point).
  var FOCAL_POINT_SELECTOR = 'input.focal-point';
  var ORIGINAL_LINK_SELECTOR = '.file-link a, a[type="original"]';
  // Scoped to the description field's own wrapper, not the first `.ck-content`
  // in the form: a hero form can carry more than one CKEditor 5 instance, and
  // the preview must only ever dress up field_description's editable.
  var DESCRIPTION_WRAPPER_SELECTOR = '[data-drupal-selector*="edit-field-description"]';
  var EDITABLE_SELECTOR = '.ck-content';

  /**
   * Reads the focal point value ("X,Y" percentages) off a widget, if any.
   *
   * @param {HTMLElement} widget
   *   The image widget wrapper.
   *
   * @return {string}
   *   A CSS background-position value.
   */
  function focalPosition(widget) {
    var input = widget.querySelector(FOCAL_POINT_SELECTOR);
    var value = input && input.value ? input.value.split(',') : null;
    if (!value || value.length !== 2 || value[0] === '' || value[1] === '') {
      return '50% 50%';
    }
    return value[0].trim() + '% ' + value[1].trim() + '%';
  }

  /**
   * Resolves the best available image URL from the image widget.
   *
   * Prefers the original file link (full-size) over the thumbnail preview.
   *
   * @param {HTMLElement} widget
   *   The image widget wrapper.
   *
   * @return {string}
   *   An image URL, or an empty string when no image is present.
   */
  function imageUrl(widget) {
    var link = widget.querySelector(ORIGINAL_LINK_SELECTOR);
    if (link && link.href) {
      return link.href;
    }
    var img = widget.querySelector(IMAGE_SELECTOR);
    return img && img.src ? img.src : '';
  }

  /**
   * Finds the field_description CKEditor 5 editable within a form, if mounted.
   *
   * @param {HTMLElement} form
   *   The media hero add/edit form.
   *
   * @return {HTMLElement|null}
   *   The `.ck-content` element scoped to field_description, or null when the
   *   field wrapper or its editable is not present (yet).
   */
  function descriptionEditable(form) {
    var wrapper = form.querySelector(DESCRIPTION_WRAPPER_SELECTOR);
    return wrapper ? wrapper.querySelector(EDITABLE_SELECTOR) : null;
  }

  /**
   * Builds or removes the preview on the CKEditor wrapper around the editable.
   *
   * The preview lands on `.ck-editor__main` (or `.ck-editor`), never on the
   * editable itself: `.ck-content` is the CKEditor 5 root editable, whose DOM
   * attributes the view renderer re-syncs on every focus/blur, dropping any
   * class or inline style it does not own. The wrapper elements are never
   * rewritten, so the preview survives focus and typing.
   *
   * @param {HTMLElement} editable
   *   The `.ck-content` editable element.
   * @param {HTMLElement} widget
   *   The image widget wrapper, used to resolve the image and focal point.
   * @param {Object} colours
   *   `{primary, secondary}` hex colours.
   */
  function applyPreview(editable, widget, colours) {
    var url = imageUrl(widget);
    var wrapper = editable.closest('.ck-editor__main')
      || editable.closest('.ck-editor')
      || editable;

    if (!url) {
      wrapper.classList.remove(WRAPPER_CLASS);
      wrapper.style.backgroundImage = '';
      wrapper.style.backgroundSize = '';
      wrapper.style.backgroundPosition = '';
      wrapper.style.removeProperty('--bioland-hero-primary');
      wrapper.style.removeProperty('--bioland-hero-secondary');
      return;
    }

    wrapper.classList.add(WRAPPER_CLASS);
    wrapper.style.setProperty('--bioland-hero-primary', colours.primary);
    wrapper.style.setProperty('--bioland-hero-secondary', colours.secondary);
    wrapper.style.backgroundImage = 'url("' + url + '")';
    wrapper.style.backgroundSize = 'cover';
    wrapper.style.backgroundPosition = focalPosition(widget);
  }

  /**
   * Watches the field_description wrapper until its CKEditor 5 editable mounts.
   *
   * @param {HTMLElement} form
   *   The media hero add/edit form.
   * @param {Function} update
   *   Re-runs the preview; returns the resolved editable, or null.
   */
  function observeEditorMount(form, update) {
    var wrapper = form.querySelector(DESCRIPTION_WRAPPER_SELECTOR);
    if (!wrapper || typeof MutationObserver === 'undefined') {
      return;
    }

    var observer = new MutationObserver(function () {
      if (update()) {
        observer.disconnect();
      }
    });
    observer.observe(wrapper, { childList: true, subtree: true });
  }

  Drupal.behaviors.biolandHeroEditorPreview = {
    attach: function (context, settings) {
      var heroSettings = settings.bioland && settings.bioland.heroPreview;
      if (!heroSettings || !heroSettings.primary || !heroSettings.secondary) {
        // No authored/fallback colours were passed down: skip rather than
        // guess a flavour's colours in JS.
        return;
      }
      var colours = {
        primary: heroSettings.primary,
        secondary: heroSettings.secondary,
      };

      // The field wrapper outlives Drupal's AJAX upload/remove round trips
      // (only its inner ajax-wrapper is swapped) and the media library pick,
      // so once() binds here exactly once per form and a MutationObserver on
      // its subtree re-runs the preview whenever the markup inside changes.
      once('bioland-hero-editor-preview', WIDGET_SELECTOR, context).forEach(function (widget) {
        var form = widget.closest('form');
        if (!form) {
          return;
        }

        var update = function () {
          var editable = descriptionEditable(form);
          if (editable) {
            applyPreview(editable, widget, colours);
          }
          return editable;
        };

        if (!update()) {
          // CKEditor 5 has not mounted its editable yet; retry once it does.
          observeEditorMount(form, update);
        }

        // Native change events bubble up from any input the wrapper currently
        // holds (focal point included), so one delegated listener survives
        // the AJAX re-renders that would orphan a per-input listener.
        widget.addEventListener('change', update);

        // The contrib focal_point widget writes its value with jQuery and
        // fires the change via jQuery's trigger(), which never reaches a
        // native listener. It does, however, move its indicator by rewriting
        // a style attribute, so watching attributes as well as children
        // catches focal point moves without a jQuery dependency.
        if (typeof MutationObserver !== 'undefined') {
          new MutationObserver(function () {
            update();
          }).observe(widget, { childList: true, subtree: true, attributes: true });
        }
      });
    }
  };
})(Drupal, once, drupalSettings);
