/**
 * @file
 * Auto summary functionality for Bioland module.
 * Automatically generates summary text from body field content.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Drupal behavior for Bioland auto summary.
   */
  Drupal.behaviors.biolandAutoSummary = {
    attach: function (context, settings) {
      // Get settings from Drupal
      const biolandSettings = settings.bioland || {};
      
      // Only proceed if auto summary is enabled
      if (biolandSettings.enableAutoSummary === false) {
        return;
      }

      // Initialize auto summary functionality
      this.initializeAutoSummary(context, biolandSettings);
    },

    /**
     * Initialize auto summary functionality.
     *
     * @param {Element} context - The context element
     * @param {Object} settings - Bioland settings from PHP
     */
    initializeAutoSummary: function (context, settings) {
      console.log('Bioland: Initializing auto summary');
      
      const bodyEl = this.getBodyField();
      const summaryEl = this.getSummaryField();
      
      if (!this.shouldAutoSummary(bodyEl, summaryEl)) {
        console.log('Bioland: Auto summary not needed');
        return;
      }

      this.startAutoSummary(bodyEl, summaryEl);
    },

    /**
     * Get the body field element.
     * @returns {Element|null} The body field element or null if not found
     */
    getBodyField: function () {
      return document.querySelector('#edit-body-0-value');
    },

    /**
     * Get the summary field element.
     * @returns {Element|null} The summary field element or null if not found
     */
    getSummaryField: function () {
      return document.querySelector('#edit-body-0-summary');
    },

    /**
     * Check if auto summary should be enabled.
     * @param {Element} bodyEl - The body field element
     * @param {Element} summaryEl - The summary field element
     * @returns {boolean} True if auto summary should be enabled
     */
    shouldAutoSummary: function (bodyEl, summaryEl) {
      if (!bodyEl || !summaryEl) return false;

      const emptyBody = !bodyEl.value || bodyEl.value.length < 10;
      const emptySummary = !summaryEl.value || summaryEl.value.length < 10;

      if (emptyBody && !emptySummary) return false;
      if (!emptyBody && !emptySummary) return false;

      if (emptyBody && emptySummary) return true;

      // if(!emptyBody && emptySummary)
      this.updateSummaryField(bodyEl, summaryEl);

      return true;
    },

    /**
     * Start auto summary functionality.
     * @param {Element} bodyEl - The body field element
     * @param {Element} summaryEl - The summary field element
     * @returns {boolean} True if auto summary started, false otherwise
     */
    startAutoSummary: function (bodyEl, summaryEl) {
      if (!bodyEl || !summaryEl) return false;

      const self = this;

      // Set up event listeners
      $('#edit-body-0-value').on('change.biolandAutoSummary', function() {
        self.updateSummaryField(bodyEl, summaryEl);
      });

      $('#edit-body-0-value').on('keydown.biolandAutoSummary', function() {
        // Use setTimeout to wait for the keypress to be processed
        setTimeout(function() {
          self.updateSummaryField(bodyEl, summaryEl);
        }, 100);
      });

      $('#edit-body-0-value').on('mouseout.biolandAutoSummary', function() {
        self.updateSummaryField(bodyEl, summaryEl);
      });

      // Remove auto summary if user manually edits summary
      $('#edit-body-0-summary').on('change.biolandAutoSummary', function() {
        self.removeAutoSummary();
      });

      console.log('Bioland: Auto summary started');
      return true;
    },

    /**
     * Remove auto summary event listeners.
     */
    removeAutoSummary: function () {
      $('#edit-body-0-value').off('change.biolandAutoSummary');
      $('#edit-body-0-value').off('keydown.biolandAutoSummary');
      $('#edit-body-0-value').off('mouseout.biolandAutoSummary');
      $('#edit-body-0-summary').off('change.biolandAutoSummary');
      console.log('Bioland: Auto summary removed');
    },

    /**
     * Update summary field with truncated body content.
     * @param {Element} bodyEl - The body field element
     * @param {Element} summaryEl - The summary field element
     */
    updateSummaryField: function (bodyEl, summaryEl) {
      if (!bodyEl || !summaryEl) return;

      console.log('Bioland: updateSummaryField called');

      const locale = document.querySelector('html').getAttribute('lang') || 'en';
      const newSummary = this.smartTruncate(this.stripHtml(bodyEl.value), 255, locale);
      
      // Only update if the content has actually changed
      if (summaryEl.value !== newSummary) {
        summaryEl.value = newSummary;
        summaryEl.dispatchEvent(new Event('change'));
      }
    },

    /**
     * Smart truncate text to specified length, preserving sentence boundaries.
     * @param {string} texts - The text to truncate
     * @param {number} length - Maximum length (default 512)
     * @param {string} locale - Locale for sentence segmentation (default 'en')
     * @returns {string} Truncated text
     */
    smartTruncate: function (texts, length = 512, locale = 'en') {
      if (!texts) return '';
      
      const text = this.stripHtml(texts);
      
      // Fallback for browsers that don't support Intl.Segmenter
      if (typeof Intl === 'undefined' || typeof Intl.Segmenter === 'undefined') {
        return this.simpleSmartTruncate(text, length);
      }

      try {
        const segmenter = new Intl.Segmenter(locale, { granularity: 'sentence' });
        const segments = Array.from(segmenter.segment(text), s => s.segment);

        let charCount = 0;
        let i = 0;
        const sentences = [];

        for (const segment of segments) {
          if (charCount + segment.length > length) break;

          sentences.push(segment);
          charCount += segment.length;
          i++;
        }

        const result = Array.isArray(sentences.join('')) ? sentences.join('')[0] : sentences.join('');
        const response = result.length > length ? result.substring(0, length) : result;
        const lastIndexOf = response.includes('.') ? response.lastIndexOf('.') + 1 : response.length;

        return response.substring(0, lastIndexOf);
      } catch (error) {
        console.warn('Bioland: Intl.Segmenter error, falling back to simple truncation:', error);
        return this.simpleSmartTruncate(text, length);
      }
    },

    /**
     * Simple smart truncate fallback for older browsers.
     * @param {string} text - The text to truncate
     * @param {number} length - Maximum length
     * @returns {string} Truncated text
     */
    simpleSmartTruncate: function (text, length) {
      if (text.length <= length) return text;
      
      let truncated = text.substring(0, length);
      const lastPeriod = truncated.lastIndexOf('.');
      const lastSpace = truncated.lastIndexOf(' ');
      
      if (lastPeriod > length - 50) {
        return truncated.substring(0, lastPeriod + 1);
      } else if (lastSpace > length - 50) {
        return truncated.substring(0, lastSpace) + '...';
      } else {
        return truncated + '...';
      }
    },

    /**
     * Strip all HTML tags from a string using regex.
     * @param {string} input - Input string
     * @returns {string} String with HTML tags removed
     */
    stripHtml: function (input) {
      if (!input) return '';
      return input.replace(/<[^>]*>/g, '');
    }
  };

})(jQuery, Drupal);
