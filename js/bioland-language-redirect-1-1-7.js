/**
 * @file
 * Bioland language redirect functionality.
 *
 * Redirects the user to the correct language URL when they change
 * the language selector in the node form sidebar.
 */

(function (Drupal) {
  'use strict';

  /**
   * Language redirect behavior for node forms.
   */
  Drupal.behaviors.biolandLanguageRedirect = {
    attach: function (context, settings) {
      var select = document.getElementById('bioland-langcode-select');
      
      if (!select || select.dataset.biolandLangRedirectInit) {
        return;
      }
      select.dataset.biolandLangRedirectInit = 'true';

      // Get the current URL path and detect the language prefix.
      var currentPath = window.location.pathname;
      var currentLangcode = this.detectLanguageFromUrl(currentPath);
      
      // Set the select to match the current URL language on load.
      if (currentLangcode && select.querySelector('option[value="' + currentLangcode + '"]')) {
        select.value = currentLangcode;
      }

      // Handle language change.
      var self = this;
      select.addEventListener('change', function () {
        var newLangcode = select.value;
        if (newLangcode && newLangcode !== currentLangcode) {
          var newUrl = self.buildNewUrl(currentPath, currentLangcode, newLangcode);
          if (newUrl !== currentPath) {
            window.location.href = newUrl;
          }
        }
      });
    },

    /**
     * Detect the language code from URL path prefix.
     *
     * @param {string} path - The URL path (e.g., /en/node/add/content)
     * @return {string|null} - The language code or null
     */
    detectLanguageFromUrl: function (path) {
      // Match language prefix pattern: /xx/ or /xx-xx/ (e.g., /en/, /fr/, /zh-hans/)
      var match = path.match(/^\/([a-z]{2}(?:-[a-z]+)?)\//i);
      if (match) {
        return match[1].toLowerCase();
      }
      return null;
    },

    /**
     * Build the new URL with the changed language prefix.
     *
     * @param {string} currentPath - Current URL path
     * @param {string} currentLang - Current language code (may be null)
     * @param {string} newLang - New language code
     * @return {string} - New URL path
     */
    buildNewUrl: function (currentPath, currentLang, newLang) {
      var newPath;
      
      if (currentLang) {
        // Replace existing language prefix.
        // Match /xx/ or /xx-xx/ at the start.
        newPath = currentPath.replace(/^\/[a-z]{2}(?:-[a-z]+)?\//i, '/' + newLang + '/');
      } else {
        // No current language prefix, add one.
        newPath = '/' + newLang + currentPath;
      }
      
      // Preserve query string if any.
      var queryString = window.location.search;
      return newPath + queryString;
    }
  };

})(Drupal);
