/**
 * @file
 * Field visibility functionality for Bioland module.
 * Controls show/hide behavior of fields based on content type selection.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Drupal behavior for Bioland field visibility.
   */
  Drupal.behaviors.biolandFieldVisibility = {
    // Track the last content type value to detect changes
    lastContentTypeValue: null,

    attach: function (context, settings) {
      // Get settings from Drupal
      const biolandSettings = settings.bioland || {};
      
      // Only proceed if field visibility is enabled
      if (biolandSettings.enableFieldVisibility === false) {
        return;
      }

      // Initialize field visibility functionality
      this.initializeFieldVisibility(context, biolandSettings);
    },

    /**
     * Initialize field visibility functionality.
     *
     * @param {Element} context - The context element
     * @param {Object} settings - Bioland settings from PHP
     */
    initializeFieldVisibility: function (context, settings) {
      console.log('Bioland: Initializing field visibility');
      
      // Get the content type field
      const contentTypeField = this.getContentTypeField();
      const contentTypeValue = contentTypeField?.value;
      
      if (!contentTypeValue) {
        console.log('Bioland: No content type field found for visibility');
        return;
      }

      console.log('Bioland: Applying field visibility for content type:', contentTypeValue);

      // Store the initial value
      this.lastContentTypeValue = contentTypeValue;

      // Apply initial field visibility
      this.applyFieldVisibility(contentTypeValue);
      
      // Hide text format elements
      this.hideTextFormat();

      // Hide summary field (always hidden)
      this.hideSummary();

      // Set up content type field change listeners
      this.setupContentTypeListeners(context);
    },

    /**
     * Apply field visibility based on content type.
     *
     * @param {string|number} contentTypeValue - The content type value
     */
    applyFieldVisibility: function (contentTypeValue) {
      if (!contentTypeValue) return;

      this.hideFields(contentTypeValue);
    },

    /**
     * Main function to hide/show fields based on content type.
     * @param {string|number} contentTypeValue - The content type value
     */
    hideFields: function (contentTypeValue) {
      if (!contentTypeValue) return;

      this.hideDates(contentTypeValue);
      this.hideUrl(contentTypeValue);
      this.hidePublished(contentTypeValue);
    },

    /**
     * Hide/show URL field wrapper based on content type.
     * @param {string|number} contentTypeValue - The content type value
     */
    hideUrl: function (contentTypeValue) {
      if (!contentTypeValue) return;

      const urlWrapper = document.querySelector('#edit-field-url-wrapper');
      
      if (!urlWrapper) return;

      if ([13, 2, 4, 11, 12, 3, 8, 5, 4, 10, 9].includes(Number(contentTypeValue))) {
        urlWrapper.style.display = 'block';
      } else {
        urlWrapper.style.display = 'none';
      }
    },

    /**
     * Hide/show published field wrapper based on content type.
     * @param {string|number} contentTypeValue - The content type value
     */
    hidePublished: function (contentTypeValue) {
      if (!contentTypeValue) return;

      const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
      
      if (!publishedWrapper) return;

      if ([13, 2, 4, 11, 12, 3, 8, 5, 4, 10, 9].includes(Number(contentTypeValue))) {
        publishedWrapper.style.display = 'block';
      } else {
        publishedWrapper.style.display = 'none';
      }
    },

    /**
     * Hide/show date field wrappers based on content type.
     * @param {string|number} contentTypeValue - The content type value
     */
    hideDates: function (contentTypeValue) {
      if (!contentTypeValue) return;

      const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
      const endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
      
      const contentTypesWithDates = [13, 2, 4, 11];
      const shouldShowDates = contentTypesWithDates.includes(Number(contentTypeValue));

      if (startDateWrapper) {
        startDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
      }

      if (endDateWrapper) {
        endDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
      }
    },

    /**
     * Hide text format elements from the body field.
     */
    hideTextFormat: function () {
      const label = document.querySelector('label[for="edit-body-0-format--2"]');
      const helpLink = document.querySelector('#edit-body-0-format-help-about');

      if (label) {
        label.style.display = 'none';
      }
      if (helpLink) {
        helpLink.style.display = 'none';
      }
    },

    /**
     * Hide the summary field (always hidden).
     */
    hideSummary: function () {
      // Hide the summary wrapper
      const summaryWrapper = document.querySelector('.text-summary-wrapper');
      
      if (summaryWrapper) {
        summaryWrapper.style.display = 'none';
      }

      // Also hide the summary textarea directly if it exists
      const summaryField = document.querySelector('textarea[data-drupal-selector="edit-body-0-summary"]');
      
      if (summaryField) {
        // Hide the parent wrapper if it exists
        const parentWrapper = summaryField.closest('.js-form-type-textarea');
        if (parentWrapper) {
          parentWrapper.style.display = 'none';
        } else {
          summaryField.style.display = 'none';
        }
      }
    },

    /**
     * Get the content type field element and its current value.
     * 
     * @returns {Object|null} Object with element and value properties, or null if not found
     */
    getContentTypeField: function () {
      const typePlacementInputEl = document.querySelector('#edit-field-type-placement');

      if (!typePlacementInputEl) return null;

      return {
        element: typePlacementInputEl,
        value: typePlacementInputEl.value
      };
    },

    /**
     * Set up event listeners for content type field changes.
     * 
     * @param {Element} context - The context element
     */
    setupContentTypeListeners: function (context) {
      const self = this;
      const contentTypeField = this.getContentTypeField();
      
      if (!contentTypeField) return;

      const fieldElement = document.querySelector('#edit-field-type-placement');
      if (!fieldElement) return;

      // Check if already initialized
      if (fieldElement.dataset.biolandFieldVisibilityInit) {
        return;
      }

      // Mark as initialized
      fieldElement.dataset.biolandFieldVisibilityInit = 'true';

      $(fieldElement).on('change.biolandFieldVisibility', function () {
        self.handleContentTypeChange();
      });
      
      $(fieldElement).on('keydown.biolandFieldVisibility', function () {
        setTimeout(function() {
          self.handleContentTypeChange();
        }, 100);
      });
      
      $(fieldElement).on('mouseout.biolandFieldVisibility', function () {
        self.handleContentTypeChange();
      });
    },

    /**
     * Handle content type field changes by updating field visibility.
     */
    handleContentTypeChange: function () {
      const updatedField = this.getContentTypeField();
      const updatedValue = updatedField?.value;
      
      console.log('Bioland: Visibility handleContentTypeChange called');
      console.log('Bioland: Visibility previous value:', this.lastContentTypeValue);
      console.log('Bioland: Visibility new value:', updatedValue);
      
      if (!updatedValue) {
        console.log('Bioland: No updated value found');
        return;
      }

      // Check if value actually changed
      if (this.lastContentTypeValue === updatedValue) {
        console.log('Bioland: Content type value unchanged, skipping');
        return;
      }

      // Update the stored value
      this.lastContentTypeValue = updatedValue;

      console.log('Bioland: Content type changed, updating field visibility:', updatedValue);
      this.applyFieldVisibility(updatedValue);
    }
  };

})(jQuery, Drupal);
