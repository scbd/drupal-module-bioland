/**
 * @file
 * Field visibility functionality for Bioland module.
 * Controls show/hide behavior of fields based on content type selection.
 */

(function (Drupal, window) {
  'use strict';

  // Prevent duplicate initialization if script is loaded multiple times
  if (Drupal.behaviors.biolandFieldVisibility) {
    return;
  }

  /**
   * Track the last content type value to detect changes
   * Using var to avoid block-scoping issues with early returns
   */
  var lastContentTypeValue = null;

  /**
   * Store settings for later use
   */
  var storedSettings = {};

  /**
   * Logger shortcut for this module
   */
  function logger() {
    return window.BiolandLogger && window.BiolandLogger.fieldVisibility 
      ? window.BiolandLogger.fieldVisibility 
      : { log: function() {}, warn: function() {}, error: function() {} };
  }

  /**
   * Initialize field visibility functionality.
   *
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   */
  function initializeFieldVisibility(context, settings) {
    logger().log('Initializing field visibility');
    
    // Get the content type field
    var contentTypeField = getContentTypeField();
    var contentTypeValue = contentTypeField ? contentTypeField.value : null;
    
    if (!contentTypeValue) {
      logger().log('No content type field found for visibility');
      return;
    }

    logger().log('Applying field visibility for content type:', contentTypeValue);

    // Store the initial value
    lastContentTypeValue = contentTypeValue;

    // Store settings for later use
    storedSettings = settings;

    // Apply initial field visibility
    applyFieldVisibility(contentTypeValue);
    
    // Hide text format elements
    hideTextFormat();

    // Set up content type field change listeners
    setupContentTypeListeners(context);
  }

  /**
   * Apply field visibility based on content type.
   *
   * @param {string|number} contentTypeValue - The content type value
   */
  function applyFieldVisibility(contentTypeValue) {
    if (!contentTypeValue) return;

    hideFields(contentTypeValue);
  }

  /**
   * Main function to hide/show fields based on content type.
   * @param {string|number} contentTypeValue - The content type value
   */
  function hideFields(contentTypeValue) {
    if (!contentTypeValue) return;

    hideDates(contentTypeValue);
    hideUrl(contentTypeValue);
    hidePublished(contentTypeValue);
  }

  /**
   * Hide/show URL field wrapper based on content type.
   * @param {string|number} contentTypeValue - The content type value
   */
  function hideUrl(contentTypeValue) {
    if (!contentTypeValue) return;

    var urlWrapper = document.querySelector('#edit-field-url-wrapper');
    
    if (!urlWrapper) return;

    // Use settings from PHP, with fallback defaults
    var urlContentTypes = storedSettings && storedSettings.urlContentTypes 
      ? storedSettings.urlContentTypes 
      : [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50];

    if (urlContentTypes.indexOf(Number(contentTypeValue)) !== -1) {
      urlWrapper.style.display = 'block';
    } else {
      urlWrapper.style.display = 'none';
    }
  }

  /**
   * Hide/show published field wrapper based on content type.
   * @param {string|number} contentTypeValue - The content type value
   */
  function hidePublished(contentTypeValue) {
    if (!contentTypeValue) return;

    var publishedWrapper = document.querySelector('#edit-field-published-wrapper');
    
    if (!publishedWrapper) return;

    // Use settings from PHP, with fallback defaults
    var publishedContentTypes = storedSettings && storedSettings.publishedContentTypes 
      ? storedSettings.publishedContentTypes 
      : [3, 5, 12];

    if (publishedContentTypes.indexOf(Number(contentTypeValue)) !== -1) {
      publishedWrapper.style.display = 'block';
    } else {
      publishedWrapper.style.display = 'none';
    }
  }

  /**
   * Hide/show date field wrappers based on content type.
   * @param {string|number} contentTypeValue - The content type value
   */
  function hideDates(contentTypeValue) {
    if (!contentTypeValue) return;

    var startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
    var endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
    
    // Use settings from PHP, with fallback defaults
    var dateRangeContentTypes = storedSettings && storedSettings.dateRangeContentTypes 
      ? storedSettings.dateRangeContentTypes 
      : [2, 3, 13];
    var shouldShowDates = dateRangeContentTypes.indexOf(Number(contentTypeValue)) !== -1;

    if (startDateWrapper) {
      startDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
    }

    if (endDateWrapper) {
      endDateWrapper.style.display = shouldShowDates ? 'block' : 'none';
    }
  }

  /**
   * Hide text format elements from the body field.
   */
  function hideTextFormat() {
    var label = document.querySelector('label[for="edit-body-0-format--2"]');
    var helpLink = document.querySelector('#edit-body-0-format-help-about');

    if (label) {
      label.style.display = 'none';
    }
    if (helpLink) {
      helpLink.style.display = 'none';
    }
  }

  /**
   * Get the content type field element and its current value.
   * 
   * @returns {Object|null} Object with element and value properties, or null if not found
   */
  function getContentTypeField() {
    var typePlacementInputEl = document.querySelector('#edit-field-type-placement');

    if (!typePlacementInputEl) return null;

    return {
      element: typePlacementInputEl,
      value: typePlacementInputEl.value
    };
  }

  /**
   * Set up event listeners for content type field changes.
   * 
   * @param {Element} context - The context element
   */
  function setupContentTypeListeners(context) {
    var contentTypeField = getContentTypeField();
    
    if (!contentTypeField) return;

    var fieldElement = document.querySelector('#edit-field-type-placement');
    if (!fieldElement) return;

    // Check if already initialized
    if (fieldElement.dataset.biolandFieldVisibilityInit) {
      return;
    }

    // Mark as initialized
    fieldElement.dataset.biolandFieldVisibilityInit = 'true';

    fieldElement.addEventListener('change', function() {
      handleContentTypeChange();
    });

    fieldElement.addEventListener('keydown', function() {
      setTimeout(function() {
        handleContentTypeChange();
      }, 100);
    });

    fieldElement.addEventListener('mouseout', function() {
      handleContentTypeChange();
    });
  }

  /**
   * Handle content type field changes by updating field visibility.
   */
  function handleContentTypeChange() {
    var updatedField = getContentTypeField();
    var updatedValue = updatedField ? updatedField.value : null;
    
    logger().log('Visibility handleContentTypeChange called');
    logger().log('Visibility previous value:', lastContentTypeValue);
    logger().log('Visibility new value:', updatedValue);
    
    if (!updatedValue) {
      logger().log('No updated value found');
      return;
    }

    // Check if value actually changed
    if (lastContentTypeValue === updatedValue) {
      logger().log('Content type value unchanged, skipping');
      return;
    }

    // Update the stored value
    lastContentTypeValue = updatedValue;

    logger().log('Content type changed, updating field visibility:', updatedValue);
    applyFieldVisibility(updatedValue);
  }

  /**
   * Drupal behavior for Bioland field visibility.
   */
  Drupal.behaviors.biolandFieldVisibility = {
    attach: function(context, settings) {
      // Get settings from Drupal
      var biolandSettings = settings.bioland || {};
      
      // Only proceed if field visibility is enabled
      if (biolandSettings.enableFieldVisibility === false) {
        return;
      }

      // Initialize field visibility functionality
      initializeFieldVisibility(context, biolandSettings);
    }
  };

})(Drupal, window);
