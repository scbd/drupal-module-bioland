/**
 * @file
 * Prefills the Component-menu link form from the picker selection.
 *
 * Attached only on Component-mode forms (form class
 * .bioland-component-menu-form, set by BiolandComponentMenuFormMode::apply()).
 * When the editor picks a component - or, for the Content Type Listing, a
 * content type - the menu link Title is prefilled with the selection's label
 * and the Link with <nolink>, matching what every existing component link
 * stores. Prefills never clobber the editor: a field is only written while it
 * is empty or still holds this script's previous prefill, so anything typed by
 * hand wins and stays.
 */
(function (Drupal, once) {
  'use strict';

  var PICKER_NAME = 'bioland_component';
  var CONTENT_TYPE_NAME = 'bioland_component_content_type';
  var CONTENT_TYPE_TOKEN = 'bl2-component-content-type';
  var NOLINK = '<nolink>';

  /**
   * Returns the label of a select's selected option, or an empty string.
   *
   * @param {HTMLSelectElement|null} select
   *   The select element.
   *
   * @return {string}
   *   The trimmed option text.
   */
  function selectedLabel(select) {
    if (!select || select.selectedIndex < 0) {
      return '';
    }
    var option = select.options[select.selectedIndex];
    return option && option.value !== '' ? option.text.trim() : '';
  }

  /**
   * Writes a value into a field unless the editor already owns it.
   *
   * @param {HTMLInputElement|null} field
   *   The target field.
   * @param {string} value
   *   The value to prefill.
   * @param {Object} state
   *   Per-form memory of what this script last wrote, keyed by field name.
   */
  function prefill(field, value, state) {
    if (!field || value === '') {
      return;
    }
    var current = field.value.trim();
    var lastAutofill = state[field.name] || '';
    if (current === '' || current === lastAutofill) {
      field.value = value;
      state[field.name] = value;
    }
  }

  Drupal.behaviors.biolandComponentMenuForm = {
    attach: function (context) {
      once('bioland-component-menu-form', 'form.bioland-component-menu-form', context).forEach(function (form) {
        var picker = form.querySelector('select[name="' + PICKER_NAME + '"]');
        var contentType = form.querySelector('select[name="' + CONTENT_TYPE_NAME + '"]');
        var title = form.querySelector('input[name="title[0][value]"]');
        var link = form.querySelector('input[name="link[0][uri]"]');
        if (!picker) {
          return;
        }

        var state = {};

        var update = function () {
          var isContentType = picker.value === CONTENT_TYPE_TOKEN;
          var label = isContentType ? selectedLabel(contentType) : selectedLabel(picker);
          prefill(title, label, state);
          prefill(link, NOLINK, state);
        };

        picker.addEventListener('change', update);
        if (contentType) {
          contentType.addEventListener('change', update);
        }
      });
    }
  };
})(Drupal, once);
