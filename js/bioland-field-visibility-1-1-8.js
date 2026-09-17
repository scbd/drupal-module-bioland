/**
 * @file
 * Field visibility functionality for Bioland module.
 * Controls show/hide behavior of fields based on content type selection.
 * 
 * Note: No module-level state. All state passed via parameters or stored in DOM data attributes.
 * Uses window.biolandGetLogger for logging (loaded via debug_logger dependency).
 */
(function(Drupal, window, document) {
  'use strict';

  /**
   * Get the content type field element and its current value.
   * 
   * @returns {Object|null} Object with element and value properties, or null if not found
   */
  const getContentTypeField = function() {
    const typePlacementInputEl = document.querySelector('#edit-field-type-placement');

    if (!typePlacementInputEl) return null;

    return {
      element: typePlacementInputEl,
      value: typePlacementInputEl.value
    };
  };

  /**
   * Hide text format elements from the body field.
   */
  const hideTextFormat = function() {
    const label = document.querySelector('label[for="edit-body-0-format--2"]');
    const helpLink = document.querySelector('#edit-body-0-format-help-about');

    if (label) {
      label.style.display = 'none';
    }
    if (helpLink) {
      helpLink.style.display = 'none';
    }
  };

  /**
   * Hide/show date field wrappers based on content type.
   * 
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const hideDates = function(contentTypeValue, settings, logger) {
    if (!contentTypeValue) return;

    const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
    const endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
    
    const dateRangeContentTypes = settings?.dateRangeContentTypes || [2, 3, 13];
    const shouldShowDates = dateRangeContentTypes.includes(Number(contentTypeValue));

    if (startDateWrapper) {
      startDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
      logger.log('Date fields:', shouldShowDates ? 'SHOWN' : 'HIDDEN', 'for content type', contentTypeValue);
    }

    if (endDateWrapper) {
      endDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
    }
  };

  /**
   * Hide/show URL field wrapper based on content type.
   * 
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const hideUrl = function(contentTypeValue, settings, logger) {
    if (!contentTypeValue) return;

    const urlWrapper = document.querySelector('#edit-field-url-wrapper');
    
    if (!urlWrapper) return;

    const urlContentTypes = settings?.urlContentTypes || [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50];
    const shouldShow = urlContentTypes.includes(Number(contentTypeValue));

    urlWrapper.style.display = shouldShow ? 'block' : 'none';
    logger.log('URL field:', shouldShow ? 'SHOWN' : 'HIDDEN', 'for content type', contentTypeValue);
  };

  /**
   * Hide/show published field wrapper based on content type.
   * 
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const hidePublished = function(contentTypeValue, settings, logger) {
    if (!contentTypeValue) return;

    const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
    
    if (!publishedWrapper) return;

    const publishedContentTypes = settings?.publishedContentTypes || [3, 5, 12];
    const shouldShow = publishedContentTypes.includes(Number(contentTypeValue));

    publishedWrapper.style.display = shouldShow ? 'block' : 'none';
    logger.log('Published field:', shouldShow ? 'SHOWN' : 'HIDDEN', 'for content type', contentTypeValue);
  };

  /**
   * Main function to hide/show fields based on content type.
   * 
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const hideFields = function(contentTypeValue, settings, logger) {
    if (!contentTypeValue) return;

    hideDates(contentTypeValue, settings, logger);
    hideUrl(contentTypeValue, settings, logger);
    hidePublished(contentTypeValue, settings, logger);
  };

  /**
   * Apply field visibility based on content type.
   *
   * @param {string|number} contentTypeValue - The content type value
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const applyFieldVisibility = function(contentTypeValue, settings, logger) {
    if (!contentTypeValue) return;

    hideFields(contentTypeValue, settings, logger);
  };

  /**
   * Handle content type field changes by updating field visibility.
   * 
   * @param {Element} fieldElement - The content type field element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const handleContentTypeChange = function(fieldElement, settings, logger) {
    const updatedValue = fieldElement ? fieldElement.value : null;
    const lastValue = fieldElement && fieldElement.dataset ? fieldElement.dataset.biolandVisibilityLastValue : null;
    
    logger.log('Visibility handleContentTypeChange called');
    logger.log('Visibility previous value:', lastValue);
    logger.log('Visibility new value:', updatedValue);
    
    if (!updatedValue) {
      logger.log('No updated value found');
      return;
    }

    if (lastValue === updatedValue) {
      logger.log('Content type value unchanged, skipping');
      return;
    }

    fieldElement.dataset.biolandVisibilityLastValue = updatedValue;

    logger.log('Content type changed, updating field visibility:', updatedValue);
    applyFieldVisibility(updatedValue, settings, logger);
  };

  /**
   * Set up event listeners for content type field changes.
   * 
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const setupContentTypeListeners = function(context, settings, logger) {
    const contentTypeField = getContentTypeField();
    
    if (!contentTypeField) return;

    const fieldElement = contentTypeField.element;
    if (!fieldElement) return;

    if (fieldElement.dataset.biolandFieldVisibilityInit) {
      return;
    }

    fieldElement.dataset.biolandFieldVisibilityInit = 'true';

    logger.log('Setting up content type field listeners');

    fieldElement.addEventListener('change', function() {
      logger.log('Content type CHANGE event fired');
      handleContentTypeChange(this, settings, logger);
    });

    fieldElement.addEventListener('keydown', function() {
      logger.log('Content type KEYDOWN event fired');
      const element = this;
      setTimeout(function() {
        handleContentTypeChange(element, settings, logger);
      }, 100);
    });

    fieldElement.addEventListener('mouseout', function() {
      handleContentTypeChange(this, settings, logger);
    });
  };

  /**
   * Initialize field visibility functionality.
   *
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const initializeFieldVisibility = function(context, settings, logger) {
    logger.log('Initializing field visibility');
    
    const contentTypeField = getContentTypeField();
    const contentTypeValue = contentTypeField ? contentTypeField.value : null;
    
    if (!contentTypeValue) {
      logger.log('No content type field found for visibility');
      return;
    }

    logger.log('Applying field visibility for content type:', contentTypeValue);

    const fieldElement = contentTypeField ? contentTypeField.element : null;
    if (fieldElement) {
      fieldElement.dataset.biolandVisibilityLastValue = contentTypeValue;
    }

    applyFieldVisibility(contentTypeValue, settings, logger);
    hideTextFormat();
    setupContentTypeListeners(context, settings, logger);
  };

  /**
   * Drupal behavior for Bioland field visibility.
   */
  Drupal.behaviors.biolandFieldVisibility = {
    attach: function(context, settings) {
      const biolandSettings = settings.bioland || {};
      const logger = window.biolandGetLogger('fieldVisibility', biolandSettings);
      
      logger.log('>>> FIELD VISIBILITY BEHAVIOR ATTACHED <<<');
      logger.log('enableFieldVisibility setting:', biolandSettings.enableFieldVisibility);
      
      if (biolandSettings.enableFieldVisibility === false) {
        logger.log('Field visibility is DISABLED, exiting');
        return;
      }

      initializeFieldVisibility(context, biolandSettings, logger);
    }
  };

})(Drupal, window, document);
