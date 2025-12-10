/**
 * @file
 * Field visibility functionality for Bioland module.
 * Controls show/hide behavior of fields based on content type selection.
 */

/**
 * Track the last content type value to detect changes
 */
let lastContentTypeValue = null;

/**
 * Store settings for later use
 */
let storedSettings = {};

/**
 * Drupal behavior for Bioland field visibility.
 */
Drupal.behaviors.biolandFieldVisibility = {
  attach(context, settings) {
    // Get settings from Drupal
    const biolandSettings = settings.bioland || {};
    
    // Only proceed if field visibility is enabled
    if (biolandSettings.enableFieldVisibility === false) {
      return;
    }

    // Initialize field visibility functionality
    initializeFieldVisibility(context, biolandSettings);
  }
};

/**
 * Initialize field visibility functionality.
 *
 * @param {Element} context - The context element
 * @param {Object} settings - Bioland settings from PHP
 */
function initializeFieldVisibility(context, settings) {
  console.log('Bioland: Initializing field visibility');
  
  // Get the content type field
  const contentTypeField = getContentTypeField();
  const contentTypeValue = contentTypeField?.value;
  
  if (!contentTypeValue) {
    console.log('Bioland: No content type field found for visibility');
    return;
  }

  console.log('Bioland: Applying field visibility for content type:', contentTypeValue);

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

  const urlWrapper = document.querySelector('#edit-field-url-wrapper');
  
  if (!urlWrapper) return;

  // Use settings from PHP, with fallback defaults
  const urlContentTypes = storedSettings?.urlContentTypes || [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50];

  if (urlContentTypes.includes(Number(contentTypeValue))) {
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

  const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
  
  if (!publishedWrapper) return;

  // Use settings from PHP, with fallback defaults
  const publishedContentTypes = storedSettings?.publishedContentTypes || [3, 5, 12];

  if (publishedContentTypes.includes(Number(contentTypeValue))) {
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

  const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
  const endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
  
  // Use settings from PHP, with fallback defaults
  const dateRangeContentTypes = storedSettings?.dateRangeContentTypes || [2, 3, 13];
  const shouldShowDates = dateRangeContentTypes.includes(Number(contentTypeValue));

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
  const label = document.querySelector('label[for="edit-body-0-format--2"]');
  const helpLink = document.querySelector('#edit-body-0-format-help-about');

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
  const typePlacementInputEl = document.querySelector('#edit-field-type-placement');

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
  const contentTypeField = getContentTypeField();
  
  if (!contentTypeField) return;

  const fieldElement = document.querySelector('#edit-field-type-placement');
  if (!fieldElement) return;

  // Check if already initialized
  if (fieldElement.dataset.biolandFieldVisibilityInit) {
    return;
  }

  // Mark as initialized
  fieldElement.dataset.biolandFieldVisibilityInit = 'true';

  fieldElement.addEventListener('change', () => {
    handleContentTypeChange();
  });

  fieldElement.addEventListener('keydown', () => {
    setTimeout(() => {
      handleContentTypeChange();
    }, 100);
  });

  fieldElement.addEventListener('mouseout', () => {
    handleContentTypeChange();
  });
}

/**
 * Handle content type field changes by updating field visibility.
 */
function handleContentTypeChange() {
  const updatedField = getContentTypeField();
  const updatedValue = updatedField?.value;
  
  console.log('Bioland: Visibility handleContentTypeChange called');
  console.log('Bioland: Visibility previous value:', lastContentTypeValue);
  console.log('Bioland: Visibility new value:', updatedValue);
  
  if (!updatedValue) {
    console.log('Bioland: No updated value found');
    return;
  }

  // Check if value actually changed
  if (lastContentTypeValue === updatedValue) {
    console.log('Bioland: Content type value unchanged, skipping');
    return;
  }

  // Update the stored value
  lastContentTypeValue = updatedValue;

  console.log('Bioland: Content type changed, updating field visibility:', updatedValue);
  applyFieldVisibility(updatedValue);
}
