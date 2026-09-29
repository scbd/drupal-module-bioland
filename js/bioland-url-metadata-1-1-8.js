/**
 * @file
 * BL-1191: fills a Related websites item's title and description from its URL.
 *
 * When the editor leaves the URL field (the native change event fires on blur,
 * only when the value changed) with a valid http(s) URL while the content type
 * is Related websites, the site's title and description are looked up through
 * the bioland.url_metadata route (server side: a browser cannot read another
 * site's HTML) and written into the Title and Body fields. Like the component
 * menu prefill, a field is only written while it is empty or still holds this
 * script's previous fill, so anything typed by hand wins and stays.
 */
(function (Drupal, once) {
  'use strict';

  var URL_SELECTOR = 'input[name="field_url[0][uri]"]';
  var TITLE_SELECTOR = 'input[name="title[0][value]"]';
  var BODY_SELECTOR = 'textarea[name="body[0][value]"]';
  var TYPE_SELECTOR = '#edit-field-type-placement';

  /**
   * Whether a value is an absolute http(s) URL with a host.
   *
   * @param {string} value
   *   The raw input value.
   *
   * @return {boolean}
   *   TRUE when it is worth looking up.
   */
  function isLookupUrl(value) {
    try {
      var parsed = new URL(value);
      return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.hostname !== '';
    }
    catch (e) {
      return false;
    }
  }

  /**
   * Escapes text for use inside an HTML paragraph.
   */
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Returns the CKEditor 5 instance bound to a textarea, if any.
   */
  function editorFor(textarea) {
    var instances = Drupal.CKEditor5Instances;
    if (!textarea || !instances) {
      return null;
    }
    var id = textarea.getAttribute('data-ckeditor5-id');
    if (id && instances.has(id)) {
      return instances.get(id);
    }
    var found = null;
    instances.forEach(function (instance) {
      if (instance.sourceElement === textarea) {
        found = instance;
      }
    });
    return found;
  }

  /**
   * Writes a value unless the editor already owns the field.
   *
   * @param {Object} io
   *   {get: function(): string, set: function(string)} accessors.
   * @param {string} value
   *   The value to write.
   * @param {Object} state
   *   Per-form memory of the last fill, keyed by key.
   * @param {string} key
   *   The state key.
   */
  function fill(io, value, state, key) {
    if (value === '') {
      return;
    }
    var current = io.get().trim();
    if (current === '' || current === (state[key] || '')) {
      io.set(value);
      state[key] = value;
    }
  }

  /**
   * Applies a lookup result to the title and body fields.
   */
  function apply(form, data, state) {
    var title = form.querySelector(TITLE_SELECTOR);
    if (title) {
      fill({
        get: function () { return title.value; },
        set: function (v) { title.value = v; title.dispatchEvent(new Event('input', {bubbles: true})); }
      }, data.title || '', state, 'title');
    }

    var body = form.querySelector(BODY_SELECTOR);
    var description = data.description ? '<p>' + escapeHtml(data.description) + '</p>' : '';
    var editor = editorFor(body);
    if (editor) {
      fill({
        get: function () { return editor.getData(); },
        set: function (v) { editor.setData(v); }
      }, description, state, 'body');
    }
    else if (body) {
      fill({
        get: function () { return body.value; },
        set: function (v) { body.value = v; body.dispatchEvent(new Event('change', {bubbles: true})); }
      }, description, state, 'body');
    }
  }

  Drupal.behaviors.biolandUrlMetadata = {
    attach: function (context, settings) {
      var config = (settings && settings.bioland) || {};
      if (!config.urlMetadataEndpoint) {
        return;
      }
      var logger = window.biolandGetLogger ? window.biolandGetLogger('urlMetadata', config) : {log: function () {}};

      once('bioland-url-metadata', URL_SELECTOR, context).forEach(function (input) {
        var form = input.form || document;
        var state = {};
        var requested = '';

        input.addEventListener('change', function () {
          var value = input.value.trim();
          var type = document.querySelector(TYPE_SELECTOR);
          if (!type || Number(type.value) !== Number(config.relatedWebsitesTid)) {
            return;
          }
          if (value === requested || !isLookupUrl(value) || (input.checkValidity && !input.checkValidity())) {
            return;
          }
          requested = value;
          var separator = config.urlMetadataEndpoint.indexOf('?') === -1 ? '?' : '&';
          fetch(config.urlMetadataEndpoint + separator + 'url=' + encodeURIComponent(value), {
            credentials: 'same-origin',
            headers: {Accept: 'application/json'}
          })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
              // A newer URL was typed while this one was in flight.
              if (data && input.value.trim() === value) {
                apply(form, data, state);
              }
            })
            .catch(function (error) {
              logger.log('URL metadata lookup failed:', error);
            });
        });
      });
    }
  };
})(Drupal, once);
