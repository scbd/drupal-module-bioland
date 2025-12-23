/**
 * @file
 * Additional fields functionality for Bioland module.
 * Manages Vue-based additional fields based on content type.
 * 
 * Note: No module-level state. All state passed via parameters or stored in DOM data attributes.
 * Uses window.biolandGetLogger for logging (loaded via debug_logger dependency).
 */
(function(Drupal, window, document) {
  'use strict';

  /**
   * Content type to additional fields mapping
   */
  const contentTypeAdditionalFields = {
    3: ['eventStatuses'], // event 
    5: ['projectStatuses', 'geoScopes'],
    8: ['orgTypes', 'govTypes'], // ministry
    9: ['ecosystemTypes'],
    12: ['documentTypes'],
  };

  /**
   * Content types that have additional fields
   */
  const contentTypesWithFields = [3, 5, 8, 9, 12];

  /**
   * Get the content type field element and its current value.
   * 
   * @returns {Object|null} Object with element and value properties, or null if not found
   */
  const getContentTypeField = function() {
    const typePlacementInputEl = document.querySelector('#edit-field-type-placement');

    if (!typePlacementInputEl) return null;

    // Get the value - could be from value property or selected option
    const value = typePlacementInputEl.tagName === 'SELECT' && typePlacementInputEl.selectedOptions.length > 0
      ? typePlacementInputEl.selectedOptions[0].value
      : typePlacementInputEl.value;

    return {
      element: typePlacementInputEl,
      value: value
    };
  };

  /**
   * Check if content type should have additional fields.
   * @param {string|number} contentTypeValue - The content type value
   * @returns {boolean} True if content type should have additional fields
   */
  const shouldMountAdditionalFields = function(contentTypeValue) {
    return contentTypeValue && contentTypesWithFields.includes(Number(contentTypeValue));
  };

  /**
   * Find wrapper element for additional fields.
   *
   * @param {Object} logger - Logger instance
   * @returns {Element|null} The wrapper element or null if not found
   */
  const findAdditionalFieldsWrapper = function(logger) {
    // Try various possible wrapper elements
    const possibleWrappers = [
      '#edit-field-tags-wrapper'
    ];

    for (const selector of possibleWrappers) {
      const element = document.querySelector(selector);
      if (element) {
        logger.log('Found wrapper element for additional fields:', selector);
        return element;
      }
    }

    // Fallback: try to find any form wrapper
    const formWrapper = document.querySelector('.node-form, form[id*="node"]');
    if (formWrapper) {
      logger.log('Using form wrapper as fallback for additional fields');
      return formWrapper;
    }

    return null;
  };

  /**
   * Get field name for additional fields.
   * 
   * IMPORTANT: The Vue component prepends "field_" to the name we provide.
   * So we need to return just the suffix (e.g., "tags" not "field_tags").
   *
   * @param {Object} logger - Logger instance
   * @returns {string} The field name WITHOUT the "field_" prefix
   */
  const getFieldName = function(logger) {
    // First, try to get it from the wrapper element ID
    const wrapper = findAdditionalFieldsWrapper(logger);
    if (wrapper && wrapper.id) {
      logger.log('Wrapper element ID:', wrapper.id);
      // Extract field name from wrapper ID like "edit-field-tags-wrapper" -> "tags"
      // Note: We extract WITHOUT "field_" prefix since Vue component adds it
      const wrapperMatch = wrapper.id.match(/edit-field[_-]([a-zA-Z0-9_]+)-wrapper/);
      if (wrapperMatch) {
        const fieldName = wrapperMatch[1];
        logger.log('Extracted field name suffix from wrapper:', fieldName);
        return fieldName;
      }
    }
    
    // Try to get base field name from existing thesaurus field
    const thesaurusField = document.querySelector('[class*="edit-scbd_field-thesaurus-additional"], [class*="edit-bioland-field-additional"]');
    
    logger.log('Looking for thesaurus field...');
    logger.log('Found thesaurus field:', thesaurusField);
    
    if (thesaurusField && thesaurusField.name) {
      logger.log('Thesaurus field name attribute:', thesaurusField.name);
      
      // Extract the suffix after "field_"
      // e.g., "field_tags[0][value2]" -> "tags"
      const match = thesaurusField.name.match(/field[_-]([a-zA-Z0-9_]+)/);
      
      logger.log('Regex match result:', match);
      
      if (match) {
        const baseName = match[1];
        logger.log('Found thesaurus field suffix for additional fields:', baseName);
        return baseName;
      }
    }
    
    logger.log('No field name found, using default');
    // Default fallback name WITHOUT "field_" prefix (Vue will add it)
    return 'bioland_additional';
  };

  /**
   * Create additional fields element mount point.
   * @param {string|number} contentTypeValue - The content type value
   * @param {Element} wrapperEl - The wrapper element to mount to
   * @returns {Element|false} The additional fields element or false if failed
   */
  const createAdditionalFieldsElementMount = function(contentTypeValue, wrapperEl) {
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
  };

  /**
   * Mount Vue app for additional fields.
   * @param {Object} params - Parameters object
   * @param {string|number} params.contentTypeValue - The content type value
   * @param {Element} params.wrapperEl - The wrapper element to mount to
   * @param {string} params.locale - The locale
   * @param {string} params.name - The field name
   * @param {Object} params.logger - Logger instance
   * @returns {boolean} True if mounted successfully, false otherwise
   */
  const mountAdditionalFieldsVueApp = function(params) {
    const contentTypeValue = params.contentTypeValue;
    const wrapperEl = params.wrapperEl;
    const locale = params.locale;
    const name = params.name;
    const logger = params.logger;

    if (!contentTypeValue || !wrapperEl) return false;

    // Check if this content type should have additional fields
    if (!contentTypesWithFields.includes(Number(contentTypeValue))) return false;

    const domains = contentTypeAdditionalFields[contentTypeValue];
    if (!domains || !Array.isArray(domains) || domains.length === 0) return false;

    logger.log('Field name suffix (passed to Vue):', name);
    
    // The Vue component prepends "field_" to the name, so we need to do the same for the hidden field
    const actualFieldName = 'field_' + name;
    const fullFieldName = actualFieldName + '[0][value]';
    
    logger.log('Actual full field name in DOM:', fullFieldName);

    // Ensure the hidden field exists BEFORE creating the mount element
    const existingHiddenField = document.querySelector('input[name="' + fullFieldName + '"]');
    
    logger.log('Looking for existing hidden field with name:', fullFieldName);
    logger.log('Found existing hidden field:', existingHiddenField);
    
    if (!existingHiddenField) {
      const hiddenField = document.createElement('input');
      hiddenField.type = 'hidden';
      hiddenField.name = fullFieldName;
      hiddenField.value = '';
      wrapperEl.insertBefore(hiddenField, wrapperEl.firstChild);
      logger.log('Created hidden field with name:', fullFieldName);
    } else {
      logger.log('Using existing hidden field with name:', fullFieldName);
    }

    // Create the mount element
    const mountElement = createAdditionalFieldsElementMount(contentTypeValue, wrapperEl);
    if (!mountElement) return false;

    // Check if Vue and the app are available
    if (typeof Vue === 'undefined' || typeof ScbdDrupalScbdFieldJs === 'undefined') {
      logger.warn('Vue or ScbdDrupalScbdFieldJs is not available for additional fields');
      return false;
    }

    try {
      const createApp = Vue.createApp;
      const App = ScbdDrupalScbdFieldJs.default;
      
      logger.log('Creating Vue app with props:');
      logger.log('  - name:', name);
      logger.log('  - locale:', locale);
      logger.log('  - domains:', domains);
      logger.log('  - isAdditionalField: true');
      
      const anApp = createApp(App, { 
        name: name, 
        description: ' ', 
        locale: locale, 
        domains: domains, 
        isAdditionalField: true 
      });

      anApp.mount('#bl-additional-fields');
      logger.log('Additional fields Vue app mounted successfully');
      return true;
    } catch (error) {
      logger.error('Error mounting additional fields Vue app:', error);
      return false;
    }
  };

  /**
   * Mount additional fields for the given content type.
   *
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} logger - Logger instance
   */
  const mountAdditionalFields = function(contentTypeValue, logger) {
    // Find the wrapper element
    const wrapperEl = findAdditionalFieldsWrapper(logger);
    if (!wrapperEl) {
      logger.warn('Could not find wrapper element for additional fields');
      return;
    }

    // Get locale
    const locale = document.querySelector('html').getAttribute('lang') || 'en';
    
    // Get field name
    const fieldName = getFieldName(logger);
    
    logger.log('Field name for Vue component:', fieldName);
    logger.log('Full field name will be:', fieldName + '[0][value]');

    // Mount the Vue app for additional fields
    const success = mountAdditionalFieldsVueApp({
      contentTypeValue: contentTypeValue,
      wrapperEl: wrapperEl,
      locale: locale,
      name: fieldName,
      logger: logger
    });

    if (success) {
      logger.log('Additional fields mounted successfully');
    } else {
      logger.warn('Failed to mount additional fields');
    }
  };

  /**
   * Handle content type field changes by updating additional fields.
   * 
   * @param {Element} fieldElement - The content type field element
   * @param {Object} logger - Logger instance
   */
  const handleContentTypeChange = function(fieldElement, logger) {
    const updatedField = getContentTypeField();
    const updatedValue = updatedField ? updatedField.value : null;
    const lastValue = fieldElement && fieldElement.dataset ? fieldElement.dataset.biolandAdditionalLastValue : null;
    
    logger.log('handleContentTypeChange called');
    logger.log('Previous value:', lastValue);
    logger.log('New value:', updatedValue);
    
    if (!updatedValue) {
      logger.log('No updated value found');
      return;
    }

    // Check if value actually changed (allow null -> value transition for edit forms)
    if (lastValue !== null && lastValue === updatedValue) {
      logger.log('Content type value unchanged, skipping');
      return;
    }

    // Update the stored value
    fieldElement.dataset.biolandAdditionalLastValue = updatedValue;

    logger.log('Content type changed, updating additional fields:', updatedValue);
    
    // Check if the new content type should have additional fields
    if (shouldMountAdditionalFields(updatedValue)) {
      logger.log('New content type SHOULD have additional fields, mounting...');
      mountAdditionalFields(updatedValue, logger);
    } else {
      logger.log('New content type should NOT have additional fields, removing if exists...');
      // Remove existing additional fields if they exist
      const existingEl = document.querySelector('#bl-additional-fields');
      if (existingEl) {
        if (existingEl.__vue_app__) {
          existingEl.__vue_app__.unmount();
        }
        existingEl.remove();
        logger.log('Removed additional fields');
      }
    }
  };

  /**
   * Set up event listeners for content type field changes.
   * 
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   */
  const setupContentTypeListeners = function(context, logger) {
    const contentTypeField = getContentTypeField();
    
    if (!contentTypeField) {
      logger.log('No content type field found for setting up listeners');
      return;
    }

    logger.log('Setting up content type listeners');

    const fieldElement = document.querySelector('#edit-field-type-placement');
    if (!fieldElement) {
      logger.warn('Content type field element not found');
      return;
    }

    // Check if already initialized
    if (fieldElement.dataset.biolandAdditionalFieldsInit) {
      logger.log('Event listeners already attached');
      return;
    }

    // Mark as initialized
    fieldElement.dataset.biolandAdditionalFieldsInit = 'true';

    fieldElement.addEventListener('change', function() {
      logger.log('Change event fired on content type field');
      handleContentTypeChange(fieldElement, logger);
    });

    fieldElement.addEventListener('keydown', function() {
      logger.log('Keydown event fired on content type field');
      setTimeout(function() {
        handleContentTypeChange(fieldElement, logger);
      }, 100);
    });

    fieldElement.addEventListener('mouseout', function() {
      logger.log('Mouseout event fired on content type field');
      handleContentTypeChange(fieldElement, logger);
    });

    logger.log('Event listeners attached successfully');
  };

  /**
   * Initialize additional fields functionality.
   *
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const initializeAdditionalFields = function(context, settings, logger) {
    logger.log('Initializing additional fields');
    
    // Always set up content type field change listeners first
    setupContentTypeListeners(context, logger);
    
    // Get the content type field
    const contentTypeField = getContentTypeField();
    const contentTypeValue = contentTypeField ? contentTypeField.value : null;
    const fieldElement = contentTypeField ? contentTypeField.element : null;
    
    if (!contentTypeValue) {
      logger.log('No initial content type value found, checking again shortly...');
      // On edit forms, the value might not be available immediately
      // Check again after a brief delay
      setTimeout(function() {
        const deferredField = getContentTypeField();
        const deferredValue = deferredField ? deferredField.value : null;
        const deferredElement = deferredField ? deferredField.element : null;
        if (deferredValue && deferredElement) {
          logger.log('Deferred check found content type value:', deferredValue);
          deferredElement.dataset.biolandAdditionalLastValue = deferredValue;
          if (shouldMountAdditionalFields(deferredValue)) {
            mountAdditionalFields(deferredValue, logger);
          }
        }
      }, 100);
      // Don't return early - continue to set up listeners
    } else {
      logger.log('Initial content type value:', contentTypeValue);
      
      // Store the initial value
      if (fieldElement) {
        fieldElement.dataset.biolandAdditionalLastValue = contentTypeValue;
      }

      // Check if this content type should have additional fields
      if (!shouldMountAdditionalFields(contentTypeValue)) {
        logger.log('No additional fields for content type:', contentTypeValue);
      } else {
        // Mount additional fields
        mountAdditionalFields(contentTypeValue, logger);
      }
    }
  };

  /**
   * Drupal behavior for Bioland additional fields.
   */
  Drupal.behaviors.biolandAdditionalFields = {
    attach: function(context, settings) {
      const biolandSettings = settings.bioland || {};
      const logger = window.biolandGetLogger('additionalFields', biolandSettings);
      
      if (biolandSettings.enableAdditionalFields === false) {
        return;
      }

      initializeAdditionalFields(context, biolandSettings, logger);
    }
  };

})(Drupal, window, document);
