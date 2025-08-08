/**
 * @file
 * Additional fields functionality for Bioland module.
 * Manages Vue-based additional fields based on content type.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Drupal behavior for Bioland additional fields.
   */
  Drupal.behaviors.biolandAdditionalFields = {
    attach: function (context, settings) {
      // Get settings from Drupal
      const biolandSettings = settings.bioland || {};
      
      // Only proceed if additional fields is enabled
      if (biolandSettings.enableAdditionalFields === false) {
        return;
      }

      // Initialize additional fields functionality
      this.initializeAdditionalFields(context, biolandSettings);
    },

    /**
     * Initialize additional fields functionality.
     *
     * @param {Element} context - The context element
     * @param {Object} settings - Bioland settings from PHP
     */
    initializeAdditionalFields: function (context, settings) {
      console.log('Bioland: Initializing additional fields');
      
      // Get the content type field
      const contentTypeField = this.getContentTypeField();
      const contentTypeValue = contentTypeField?.value;
      
      if (!contentTypeValue) {
        console.log('Bioland: No content type field found for additional fields');
        return;
      }

      // Check if this content type should have additional fields
      if (!this.shouldMountAdditionalFields(contentTypeValue)) {
        console.log('Bioland: No additional fields for content type:', contentTypeValue);
        return;
      }

      // Mount additional fields
      this.mountAdditionalFields(contentTypeValue);

      // Set up content type field change listeners
      this.setupContentTypeListeners(context);
    },

    /**
     * Mount additional fields for the given content type.
     *
     * @param {string|number} contentTypeValue - The content type value
     */
    mountAdditionalFields: function (contentTypeValue) {
      // Find the wrapper element
      const wrapperEl = this.findAdditionalFieldsWrapper();
      if (!wrapperEl) {
        console.warn('Bioland: Could not find wrapper element for additional fields');
        return;
      }

      // Get locale
      const locale = document.querySelector('html').getAttribute('lang') || 'en';
      
      // Get field name
      const fieldName = this.getFieldName();

      // Mount the Vue app for additional fields
      const success = this.mountAdditionalFieldsVueApp({
        contentTypeValue: contentTypeValue,
        wrapperEl: wrapperEl,
        locale: locale,
        name: fieldName
      });

      if (success) {
        console.log('Bioland: Additional fields mounted successfully');
      } else {
        console.warn('Bioland: Failed to mount additional fields');
      }
    },

    /**
     * Content type to additional fields mapping
     */
    contentTypeAdditionalFields: {
      3: ['eventStatuses'], // event 
      5: ['projectStatuses', 'geoScopes'],
      8: ['orgTypes', 'govTypes'], // ministry
      9: ['ecosystemTypes'],
      12: ['documentTypes'],
    },

    /**
     * Content types that have additional fields
     */
    contentTypesWithFields: [3, 5, 8, 9, 12],

    /**
     * Check if content type should have additional fields.
     * @param {string|number} contentTypeValue - The content type value
     * @returns {boolean} True if content type should have additional fields
     */
    shouldMountAdditionalFields: function (contentTypeValue) {
      return contentTypeValue && this.contentTypesWithFields.includes(Number(contentTypeValue));
    },

    /**
     * Create additional fields element mount point.
     * @param {string|number} contentTypeValue - The content type value
     * @param {Element} wrapperEl - The wrapper element to mount to
     * @returns {Element|false} The additional fields element or false if failed
     */
    createAdditionalFieldsElementMount: function (contentTypeValue, wrapperEl) {
      if (!contentTypeValue || !wrapperEl) return false;

      // Clean up existing element
      const existingEl = document.querySelector('#bl-additional-fields');
      if (existingEl) {
        if (existingEl.__vue_app__) {
          existingEl.__vue_app__.unmount();
        }
        existingEl.remove();
      }

      const additionalFieldsEl = document.createElement('div');
      additionalFieldsEl.setAttribute('id', 'bl-additional-fields');
      wrapperEl.insertBefore(additionalFieldsEl, wrapperEl.firstChild);

      return additionalFieldsEl;
    },

    /**
     * Mount Vue app for additional fields.
     * @param {Object} params - Parameters object
     * @param {string|number} params.contentTypeValue - The content type value
     * @param {Element} params.wrapperEl - The wrapper element to mount to
     * @param {string} params.locale - The locale
     * @param {string} params.name - The field name
     * @returns {boolean} True if mounted successfully, false otherwise
     */
    mountAdditionalFieldsVueApp: function ({ contentTypeValue, wrapperEl, locale, name }) {
      if (!contentTypeValue || !wrapperEl) return false;

      // Check if this content type should have additional fields
      if (!this.contentTypesWithFields.includes(Number(contentTypeValue))) return false;

      const domains = this.contentTypeAdditionalFields[contentTypeValue];
      if (!domains || !Array.isArray(domains) || domains.length === 0) return false;

      // Create the mount element
      const mountElement = this.createAdditionalFieldsElementMount(contentTypeValue, wrapperEl);
      if (!mountElement) return false;

      // Check if Vue and the app are available
      if (typeof Vue === 'undefined' || typeof ScbdDrupalScbdFieldJs === 'undefined') {
        console.warn('Bioland: Vue or ScbdDrupalScbdFieldJs is not available for additional fields');
        return false;
      }

      try {
        const { createApp } = Vue;
        const App = ScbdDrupalScbdFieldJs.default;
        const anApp = createApp(App, { 
          name, 
          description: ' ', 
          locale, 
          domains, 
          isAdditionalField: true 
        });

        anApp.mount('#bl-additional-fields');
        console.log('Bioland: Additional fields Vue app mounted successfully');
        return true;
      } catch (error) {
        console.error('Bioland: Error mounting additional fields Vue app:', error);
        return false;
      }
    },

    /**
     * Find wrapper element for additional fields.
     *
     * @returns {Element|null} The wrapper element or null if not found
     */
    findAdditionalFieldsWrapper: function () {
      // Try various possible wrapper elements
      const possibleWrappers = [
        '#edit-field-type-placement-wrapper',
        '#edit-field-scbd-thesaurus-wrapper',
        '.field--name-field-type-placement',
        '.field--name-field-scbd-thesaurus',
        '.form-item-field-type-placement',
        '.form-item-field-scbd-thesaurus'
      ];

      for (const selector of possibleWrappers) {
        const element = document.querySelector(selector);
        if (element) {
          console.log('Bioland: Found wrapper element for additional fields:', selector);
          return element;
        }
      }

      // Fallback: try to find any form wrapper
      const formWrapper = document.querySelector('.node-form, form[id*="node"]');
      if (formWrapper) {
        console.log('Bioland: Using form wrapper as fallback for additional fields');
        return formWrapper;
      }

      return null;
    },

    /**
     * Get field name for additional fields.
     *
     * @returns {string} The field name
     */
    getFieldName: function () {
      // Try to get name from existing thesaurus field
      const thesaurusField = document.querySelector('[name*="field_scbd_thesaurus"], [name*="field_type_placement"]');
      if (thesaurusField && thesaurusField.name) {
        return thesaurusField.name;
      }
      
      // Default fallback name
      return 'field_bioland_additional';
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

      // Set up event handlers for content type placement field changes
      $('#edit-field-type-placement', context).once('bioland-additional-fields').on('change keydown mouseout', function () {
        self.handleContentTypeChange();
      });
    },

    /**
     * Handle content type field changes by updating additional fields.
     */
    handleContentTypeChange: function () {
      const updatedField = this.getContentTypeField();
      const updatedValue = updatedField?.value;
      
      if (!updatedValue) return;

      console.log('Bioland: Content type changed, updating additional fields:', updatedValue);
      this.mountAdditionalFields(updatedValue);
    }
  };

})(jQuery, Drupal);
