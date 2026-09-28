/**
 * @file
 * "Edit image" toggle for the toast image editor under a media image field.
 *
 * BiolandMediaImageEditor renders the contrib editor's markup directly under
 * the image widget. On media that already has a saved file it starts
 * collapsed (a CSS class that removes its height but keeps its width, so the
 * tui.ImageEditor instance the contrib behavior creates still measures a real
 * container). This behavior only flips that class and the button's label and
 * aria-expanded state; the editor and its save path are untouched.
 */
(function (Drupal, once) {
  'use strict';

  var COLLAPSED_CLASS = 'bioland-image-editor--collapsed';

  /**
   * Applies the expanded/collapsed state to a toggle and its editor region.
   *
   * @param {HTMLElement} button
   *   The toggle button.
   * @param {HTMLElement} editor
   *   The editor fieldset it controls.
   * @param {boolean} expanded
   *   Whether the editor should be visible.
   */
  function setExpanded(button, editor, expanded) {
    editor.classList.toggle(COLLAPSED_CLASS, !expanded);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    var label = expanded ? button.dataset.labelHide : button.dataset.labelShow;
    if (label) {
      button.textContent = label;
    }
    if (expanded) {
      // The editor may have been created while collapsed; nudge tui to lay
      // out its canvas against the now-visible container.
      window.dispatchEvent(new Event('resize'));
    }
  }

  Drupal.behaviors.biolandMediaImageEditor = {
    attach: function (context) {
      once('bioland-media-image-editor', '[data-bioland-image-editor-toggle]', context).forEach(function (button) {
        var editor = document.getElementById(button.getAttribute('aria-controls'));
        if (!editor) {
          return;
        }
        button.addEventListener('click', function (event) {
          event.preventDefault();
          setExpanded(button, editor, editor.classList.contains(COLLAPSED_CLASS));
        });
      });
    }
  };

})(Drupal, once);
