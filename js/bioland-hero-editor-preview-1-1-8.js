/**
 * @file
 * Previews the front-end hero look inside the media hero edit/add form.
 *
 * Once field_media_image has an image, the field_description CKEditor 5
 * editable area is dressed up to match hero-image.vue on the front end: the
 * image as a cover background, the same tint + overlay gradient layers, and
 * white text. Purely cosmetic - the editor content and its saved value are
 * untouched, only the editable surface's own background/foreground styling.
 *
 * Colours come from drupalSettings.bioland.heroPreview (the site's
 * theme.hero.primary / theme.hero.secondary, with the same BL2/BSL fallback
 * BiolandComponentMenuFormMode::primaryColor() uses - see
 * bioland_hero_editor_preview_settings() in bioland.module).
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  var WRAPPER_CLASS = 'bioland-hero-preview';
  var WIDGET_SELECTOR = '[data-drupal-selector*="edit-field-media-image"]';
  var IMAGE_SELECTOR = 'img';
  var FOCAL_POINT_SELECTOR = 'input.focal-point';
  var ORIGINAL_LINK_SELECTOR = '.file-link a, a[type="original"]';

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
   * Builds or removes the preview overlay inside the CKEditor editable area.
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
    var wrapper = editable.closest('.ck-editor__editable') || editable;

    if (!url) {
      wrapper.classList.remove(WRAPPER_CLASS);
      wrapper.style.backgroundImage = '';
      wrapper.style.backgroundPosition = '';
      return;
    }

    wrapper.classList.add(WRAPPER_CLASS);
    wrapper.style.setProperty('--bioland-hero-primary', colours.primary);
    wrapper.style.setProperty('--bioland-hero-secondary', colours.secondary);
    wrapper.style.backgroundImage = 'url("' + url + '")';
    wrapper.style.backgroundSize = 'cover';
    wrapper.style.backgroundPosition = focalPosition(widget);
  }

  Drupal.behaviors.biolandHeroEditorPreview = {
    attach: function (context, settings) {
      var heroSettings = (settings.bioland && settings.bioland.heroPreview) || {};
      var colours = {
        primary: heroSettings.primary || '#009edb',
        secondary: heroSettings.secondary || '#16c56e',
      };

      // Attached to the image widget wrapper rather than the form itself:
      // Drupal's AJAX upload/remove callback replaces just this wrapper, so
      // once() re-fires on the freshly-inserted replacement, keeping the
      // preview in sync without a MutationObserver.
      once('bioland-hero-editor-preview', WIDGET_SELECTOR, context).forEach(function (widget) {
        var form = widget.closest('form');
        if (!form) {
          return;
        }

        var update = function () {
          var editable = form.querySelector('.ck-content');
          if (editable) {
            applyPreview(editable, widget, colours);
          }
        };

        update();
        widget.addEventListener('change', update);

        var focalInput = widget.querySelector(FOCAL_POINT_SELECTOR);
        if (focalInput) {
          focalInput.addEventListener('change', update);
        }
      });
    }
  };
})(Drupal, once, drupalSettings);
