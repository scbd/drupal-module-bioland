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
  console.log('Bioland: Initializing additional fields');
  
  // Always set up content type field change listeners first
  setupContentTypeListeners(context);
  
  // Get the content type field
  const contentTypeField = getContentTypeField();
  const contentTypeValue = contentTypeField?.value;
  
  if (!contentTypeValue) {
    console.log('Bioland: No initial content type value found, checking again shortly...');
    // On edit forms, the value might not be available immediately
    // Check again after a brief delay
    setTimeout(function() {
      const deferredField = getContentTypeField();
      const deferredValue = deferredField?.value;
      if (deferredValue) {
        console.log('Bioland: Deferred check found content type value:', deferredValue);
        lastContentTypeValue = deferredValue;
        if (shouldMountAdditionalFields(deferredValue)) {
          mountAdditionalFields(deferredValue);
        }
      }
    }, 100);
    // Don't return early - continue to set up listeners
  } else {
    console.log('Bioland: Initial content type value:', contentTypeValue);
    
    // Store the initial value
    lastContentTypeValue = contentTypeValue;

    // Check if this content type should have additional fields
    if (!shouldMountAdditionalFields(contentTypeValue)) {
      console.log('Bioland: No additional fields for content type:', contentTypeValue);
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
    console.warn('Bioland: Could not find wrapper element for additional fields');
    return;
  }

  // Get locale
  const locale = document.querySelector('html').getAttribute('lang') || 'en';
  
  // Get field name
  const fieldName = getFieldName();
  
  console.log('Bioland: Field name for Vue component:', fieldName);
  console.log('Bioland: Full field name will be:', fieldName + '[0][value]');

  // Mount the Vue app for additional fields
  const success = mountAdditionalFieldsVueApp({
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

  console.log('Bioland: Field name suffix (passed to Vue):', name);
  
  // The Vue component prepends "field_" to the name, so we need to do the same for the hidden field
  const actualFieldName = 'field_' + name;
  const fullFieldName = actualFieldName + '[0][value]';
  
  console.log('Bioland: Actual full field name in DOM:', fullFieldName);

  // Ensure the hidden field exists BEFORE creating the mount element
  let hiddenField = document.querySelector('input[name="' + fullFieldName + '"]');
  
  console.log('Bioland: Looking for existing hidden field with name:', fullFieldName);
  console.log('Bioland: Found existing hidden field:', hiddenField);
  
  if (!hiddenField) {
    hiddenField = document.createElement('input');
    hiddenField.type = 'hidden';
    hiddenField.name = fullFieldName;
    hiddenField.value = '';
    wrapperEl.insertBefore(hiddenField, wrapperEl.firstChild);
    console.log('Bioland: Created hidden field with name:', fullFieldName);
  } else {
    console.log('Bioland: Using existing hidden field with name:', fullFieldName);
  }

  // Create the mount element
  const mountElement = createAdditionalFieldsElementMount(contentTypeValue, wrapperEl);
  if (!mountElement) return false;

  // Check if Vue and the app are available
  if (typeof Vue === 'undefined' || typeof ScbdDrupalScbdFieldJs === 'undefined') {
    console.warn('Bioland: Vue or ScbdDrupalScbdFieldJs is not available for additional fields');
    return false;
  }

  try {
    const { createApp } = Vue;
    const App = ScbdDrupalScbdFieldJs.default;
    
    console.log('Bioland: Creating Vue app with props:');
    console.log('  - name:', name);
    console.log('  - locale:', locale);
    console.log('  - domains:', domains);
    console.log('  - isAdditionalField: true');
    
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
    console.log('Bioland: Wrapper element ID:', wrapper.id);
    // Extract field name from wrapper ID like "edit-field-tags-wrapper" -> "tags"
    // Note: We extract WITHOUT "field_" prefix since Vue component adds it
    const wrapperMatch = wrapper.id.match(/edit-field[_-]([a-zA-Z0-9_]+)-wrapper/);
    if (wrapperMatch) {
      const fieldName = wrapperMatch[1];
      console.log('Bioland: Extracted field name suffix from wrapper:', fieldName);
      return fieldName;
    }
  }
  
  // Try to get base field name from existing thesaurus field
  const thesaurusField = document.querySelector('[class*="edit-scbd_field-thesaurus-additional"], [class*="edit-bioland-field-additional"]');
  
  console.log('Bioland: Looking for thesaurus field...');
  console.log('Bioland: Found thesaurus field:', thesaurusField);
  
  if (thesaurusField && thesaurusField.name) {
    console.log('Bioland: Thesaurus field name attribute:', thesaurusField.name);
    
    // Extract the suffix after "field_"
    // e.g., "field_tags[0][value2]" -> "tags"
    const match = thesaurusField.name.match(/field[_-]([a-zA-Z0-9_]+)/);
    
    console.log('Bioland: Regex match result:', match);
    
    if (match) {
      const baseName = match[1];
      console.log('Bioland: Found thesaurus field suffix for additional fields:', baseName);
      return baseName;
    }
  }
  
  console.log('Bioland: No field name found, using default');
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
    console.log('Bioland: No content type field found for setting up listeners');
    return;
  }

  console.log('Bioland: Setting up content type listeners');

  const fieldElement = document.querySelector('#edit-field-type-placement');
  if (!fieldElement) {
    console.warn('Bioland: Content type field element not found');
    return;
  }

  // Check if already initialized
  if (fieldElement.dataset.biolandAdditionalFieldsInit) {
    console.log('Bioland: Event listeners already attached');
    return;
  }

  // Mark as initialized
  fieldElement.dataset.biolandAdditionalFieldsInit = 'true';

  fieldElement.addEventListener('change', () => {
    console.log('Bioland: Change event fired on content type field');
    handleContentTypeChange();
  });

  fieldElement.addEventListener('keydown', () => {
    console.log('Bioland: Keydown event fired on content type field');
    setTimeout(() => {
      handleContentTypeChange();
    }, 100);
  });

  fieldElement.addEventListener('mouseout', () => {
    console.log('Bioland: Mouseout event fired on content type field');
    handleContentTypeChange();
  });

  console.log('Bioland: Event listeners attached successfully');
}

/**
 * Handle content type field changes by updating additional fields.
 */
function handleContentTypeChange() {
  const updatedField = getContentTypeField();
  const updatedValue = updatedField?.value;
  
  console.log('Bioland: handleContentTypeChange called');
  console.log('Bioland: Previous value:', lastContentTypeValue);
  console.log('Bioland: New value:', updatedValue);
  
  if (!updatedValue) {
    console.log('Bioland: No updated value found');
    return;
  }

  // Check if value actually changed (allow null -> value transition for edit forms)
  if (lastContentTypeValue !== null && lastContentTypeValue === updatedValue) {
    console.log('Bioland: Content type value unchanged, skipping');
    return;
  }

  // Update the stored value
  lastContentTypeValue = updatedValue;

  console.log('Bioland: Content type changed, updating additional fields:', updatedValue);
  
  // Check if the new content type should have additional fields
  if (shouldMountAdditionalFields(updatedValue)) {
    console.log('Bioland: New content type SHOULD have additional fields, mounting...');
    mountAdditionalFields(updatedValue);
  } else {
    console.log('Bioland: New content type should NOT have additional fields, removing if exists...');
    // Remove existing additional fields if they exist
    const existingEl = document.querySelector('#bl-additional-fields');
    if (existingEl) {
      if (existingEl.__vue_app__) {
        existingEl.__vue_app__.unmount();
      }
      existingEl.remove();
      console.log('Bioland: Removed additional fields');
    }
  }
}
