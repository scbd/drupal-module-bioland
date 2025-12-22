/**
 * @file
 * Additional fields functionality for Bioland module.
 * Manages Vue-based additional fields based on content type.
 */

/**
 * Track the last content type value to detect changes
 */
let lastContentTypeValue = null;

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
 * Logger shortcut for this module
 */
const logger = () => window.BiolandLogger?.additionalFields || { log: () => {}, warn: () => {}, error: () => {} };

/**
 * Drupal behavior for Bioland additional fields.
 */
Drupal.behaviors.biolandAdditionalFields = {
  attach(context, settings) {
    // Get settings from Drupal
    const biolandSettings = settings.bioland || {};
    
    // Only proceed if additional fields is enabled
    if (biolandSettings.enableAdditionalFields === false) {
      return;
    }

    // Initialize additional fields functionality
    initializeAdditionalFields(context, biolandSettings);
  }
};

/**
 * Initialize additional fields functionality.
 *
 * @param {Element} context - The context element
 * @param {Object} settings - Bioland settings from PHP
 */
function initializeAdditionalFields(context, settings) {
  logger().log('Initializing additional fields');
  
  // Always set up content type field change listeners first
  setupContentTypeListeners(context);
  
  // Get the content type field
  const contentTypeField = getContentTypeField();
  const contentTypeValue = contentTypeField?.value;
  
  if (!contentTypeValue) {
    logger().log('No initial content type value found, checking again shortly...');
    // On edit forms, the value might not be available immediately
    // Check again after a brief delay
    setTimeout(function() {
      const deferredField = getContentTypeField();
      const deferredValue = deferredField?.value;
      if (deferredValue) {
        logger().log('Deferred check found content type value:', deferredValue);
        lastContentTypeValue = deferredValue;
        if (shouldMountAdditionalFields(deferredValue)) {
          mountAdditionalFields(deferredValue);
        }
      }
    }, 100);
    // Don't return early - continue to set up listeners
  } else {
    logger().log('Initial content type value:', contentTypeValue);
    
    // Store the initial value
    lastContentTypeValue = contentTypeValue;

    // Check if this content type should have additional fields
    if (!shouldMountAdditionalFields(contentTypeValue)) {
      logger().log('No additional fields for content type:', contentTypeValue);
    } else {
      // Mount additional fields
      mountAdditionalFields(contentTypeValue);
    }
  }
}

/**
 * Mount additional fields for the given content type.
 *
 * @param {string|number} contentTypeValue - The content type value
 */
function mountAdditionalFields(contentTypeValue) {
  // Find the wrapper element
  const wrapperEl = findAdditionalFieldsWrapper();
  if (!wrapperEl) {
    logger().warn('Could not find wrapper element for additional fields');
    return;
  }

  // Get locale
  const locale = document.querySelector('html').getAttribute('lang') || 'en';
  
  // Get field name
  const fieldName = getFieldName();
  
  logger().log('Field name for Vue component:', fieldName);
  logger().log('Full field name will be:', fieldName + '[0][value]');

  // Mount the Vue app for additional fields
  const success = mountAdditionalFieldsVueApp({
    contentTypeValue: contentTypeValue,
    wrapperEl: wrapperEl,
    locale: locale,
    name: fieldName
  });

  if (success) {
    logger().log('Additional fields mounted successfully');
  } else {
    logger().warn('Failed to mount additional fields');
  }
}

/**
 * Check if content type should have additional fields.
 * @param {string|number} contentTypeValue - The content type value
 * @returns {boolean} True if content type should have additional fields
 */
function shouldMountAdditionalFields(contentTypeValue) {
  return contentTypeValue && contentTypesWithFields.includes(Number(contentTypeValue));
}

/**
 * Create additional fields element mount point.
 * @param {string|number} contentTypeValue - The content type value
 * @param {Element} wrapperEl - The wrapper element to mount to
 * @returns {Element|false} The additional fields element or false if failed
 */
function createAdditionalFieldsElementMount(contentTypeValue, wrapperEl) {
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
}

/**
 * Mount Vue app for additional fields.
 * @param {Object} params - Parameters object
 * @param {string|number} params.contentTypeValue - The content type value
 * @param {Element} params.wrapperEl - The wrapper element to mount to
 * @param {string} params.locale - The locale
 * @param {string} params.name - The field name
 * @returns {boolean} True if mounted successfully, false otherwise
 */
function mountAdditionalFieldsVueApp({ contentTypeValue, wrapperEl, locale, name }) {
  if (!contentTypeValue || !wrapperEl) return false;

  // Check if this content type should have additional fields
  if (!contentTypesWithFields.includes(Number(contentTypeValue))) return false;

  const domains = contentTypeAdditionalFields[contentTypeValue];
  if (!domains || !Array.isArray(domains) || domains.length === 0) return false;

  logger().log('Field name suffix (passed to Vue):', name);
  
  // The Vue component prepends "field_" to the name, so we need to do the same for the hidden field
  const actualFieldName = 'field_' + name;
  const fullFieldName = actualFieldName + '[0][value]';
  
  logger().log('Actual full field name in DOM:', fullFieldName);

  // Ensure the hidden field exists BEFORE creating the mount element
  let hiddenField = document.querySelector('input[name="' + fullFieldName + '"]');
  
  logger().log('Looking for existing hidden field with name:', fullFieldName);
  logger().log('Found existing hidden field:', hiddenField);
  
  if (!hiddenField) {
    hiddenField = document.createElement('input');
    hiddenField.type = 'hidden';
    hiddenField.name = fullFieldName;
    hiddenField.value = '';
    wrapperEl.insertBefore(hiddenField, wrapperEl.firstChild);
    logger().log('Created hidden field with name:', fullFieldName);
  } else {
    logger().log('Using existing hidden field with name:', fullFieldName);
  }

  // Create the mount element
  const mountElement = createAdditionalFieldsElementMount(contentTypeValue, wrapperEl);
  if (!mountElement) return false;

  // Check if Vue and the app are available
  if (typeof Vue === 'undefined' || typeof ScbdDrupalScbdFieldJs === 'undefined') {
    logger().warn('Vue or ScbdDrupalScbdFieldJs is not available for additional fields');
    return false;
  }

  try {
    const { createApp } = Vue;
    const App = ScbdDrupalScbdFieldJs.default;
    
    logger().log('Creating Vue app with props:');
    logger().log('  - name:', name);
    logger().log('  - locale:', locale);
    logger().log('  - domains:', domains);
    logger().log('  - isAdditionalField: true');
    
    const anApp = createApp(App, { 
      name, 
      description: ' ', 
      locale, 
      domains, 
      isAdditionalField: true 
    });

    anApp.mount('#bl-additional-fields');
    logger().log('Additional fields Vue app mounted successfully');
    return true;
  } catch (error) {
    logger().error('Error mounting additional fields Vue app:', error);
    return false;
  }
}

/**
 * Find wrapper element for additional fields.
 *
 * @returns {Element|null} The wrapper element or null if not found
 */
function findAdditionalFieldsWrapper() {
  // Try various possible wrapper elements
  const possibleWrappers = [

    '#edit-field-tags-wrapper'
  ];

  for (const selector of possibleWrappers) {
    const element = document.querySelector(selector);
    if (element) {
      logger().log('Found wrapper element for additional fields:', selector);
      return element;
    }
  }

  // Fallback: try to find any form wrapper
  const formWrapper = document.querySelector('.node-form, form[id*="node"]');
  if (formWrapper) {
    logger().log('Using form wrapper as fallback for additional fields');
    return formWrapper;
  }

  return null;
}

/**
 * Get field name for additional fields.
 * 
 * IMPORTANT: The Vue component prepends "field_" to the name we provide.
 * So we need to return just the suffix (e.g., "tags" not "field_tags").
 *
 * @returns {string} The field name WITHOUT the "field_" prefix
 */
function getFieldName() {
  // First, try to get it from the wrapper element ID
  const wrapper = findAdditionalFieldsWrapper();
  if (wrapper && wrapper.id) {
    logger().log('Wrapper element ID:', wrapper.id);
    // Extract field name from wrapper ID like "edit-field-tags-wrapper" -> "tags"
    // Note: We extract WITHOUT "field_" prefix since Vue component adds it
    const wrapperMatch = wrapper.id.match(/edit-field[_-]([a-zA-Z0-9_]+)-wrapper/);
    if (wrapperMatch) {
      const fieldName = wrapperMatch[1];
      logger().log('Extracted field name suffix from wrapper:', fieldName);
      return fieldName;
    }
  }
  
  // Try to get base field name from existing thesaurus field
  const thesaurusField = document.querySelector('[class*="edit-scbd_field-thesaurus-additional"], [class*="edit-bioland-field-additional"]');
  
  logger().log('Looking for thesaurus field...');
  logger().log('Found thesaurus field:', thesaurusField);
  
  if (thesaurusField && thesaurusField.name) {
    logger().log('Thesaurus field name attribute:', thesaurusField.name);
    
    // Extract the suffix after "field_"
    // e.g., "field_tags[0][value2]" -> "tags"
    const match = thesaurusField.name.match(/field[_-]([a-zA-Z0-9_]+)/);
    
    logger().log('Regex match result:', match);
    
    if (match) {
      const baseName = match[1];
      logger().log('Found thesaurus field suffix for additional fields:', baseName);
      return baseName;
    }
  }
  
  logger().log('No field name found, using default');
  // Default fallback name WITHOUT "field_" prefix (Vue will add it)
  return 'bioland_additional';
}

/**
 * Get the content type field element and its current value.
 * 
 * @returns {Object|null} Object with element and value properties, or null if not found
 */
function getContentTypeField() {
  const typePlacementInputEl = document.querySelector('#edit-field-type-placement');

  if (!typePlacementInputEl) return null;

  // Get the value - could be from value property or selected option
  let value = typePlacementInputEl.value;
  
  // If it's a select element and has a selected option, use that
  if (typePlacementInputEl.tagName === 'SELECT' && typePlacementInputEl.selectedOptions.length > 0) {
    value = typePlacementInputEl.selectedOptions[0].value;
  }

  return {
    element: typePlacementInputEl,
    value: value
  };
}

/**
 * Set up event listeners for content type field changes.
 * 
 * @param {Element} context - The context element
 */
function setupContentTypeListeners(context) {
  const contentTypeField = getContentTypeField();
  
  if (!contentTypeField) {
    logger().log('No content type field found for setting up listeners');
    return;
  }

  logger().log('Setting up content type listeners');

  const fieldElement = document.querySelector('#edit-field-type-placement');
  if (!fieldElement) {
    logger().warn('Content type field element not found');
    return;
  }

  // Check if already initialized
  if (fieldElement.dataset.biolandAdditionalFieldsInit) {
    logger().log('Event listeners already attached');
    return;
  }

  // Mark as initialized
  fieldElement.dataset.biolandAdditionalFieldsInit = 'true';

  fieldElement.addEventListener('change', () => {
    logger().log('Change event fired on content type field');
    handleContentTypeChange();
  });

  fieldElement.addEventListener('keydown', () => {
    logger().log('Keydown event fired on content type field');
    setTimeout(() => {
      handleContentTypeChange();
    }, 100);
  });

  fieldElement.addEventListener('mouseout', () => {
    logger().log('Mouseout event fired on content type field');
    handleContentTypeChange();
  });

  logger().log('Event listeners attached successfully');
}

/**
 * Handle content type field changes by updating additional fields.
 */
function handleContentTypeChange() {
  const updatedField = getContentTypeField();
  const updatedValue = updatedField?.value;
  
  logger().log('handleContentTypeChange called');
  logger().log('Previous value:', lastContentTypeValue);
  logger().log('New value:', updatedValue);
  
  if (!updatedValue) {
    logger().log('No updated value found');
    return;
  }

  // Check if value actually changed (allow null -> value transition for edit forms)
  if (lastContentTypeValue !== null && lastContentTypeValue === updatedValue) {
    logger().log('Content type value unchanged, skipping');
    return;
  }

  // Update the stored value
  lastContentTypeValue = updatedValue;

  logger().log('Content type changed, updating additional fields:', updatedValue);
  
  // Check if the new content type should have additional fields
  if (shouldMountAdditionalFields(updatedValue)) {
    logger().log('New content type SHOULD have additional fields, mounting...');
    mountAdditionalFields(updatedValue);
  } else {
    logger().log('New content type should NOT have additional fields, removing if exists...');
    // Remove existing additional fields if they exist
    const existingEl = document.querySelector('#bl-additional-fields');
    if (existingEl) {
      if (existingEl.__vue_app__) {
        existingEl.__vue_app__.unmount();
      }
      existingEl.remove();
      logger().log('Removed additional fields');
    }
  }
}
