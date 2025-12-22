/**
 * @file
 * Auto summary functionality for Bioland module.
 * Automatically generates summary text from body field content.
 * Supports CKEditor 4, CKEditor 5, and plain textareas.
 */

/**
 * Timeout reference for contenteditable monitoring
 */
let contentEditableTimeout = null;

/**
 * Logger shortcut for this module
 */
const logger = () => window.BiolandLogger?.autoSummary || { log: () => {}, warn: () => {}, error: () => {} };

/**
 * Drupal behavior for Bioland auto summary.
 */
Drupal.behaviors.biolandAutoSummary = {
  attach(context, settings) {
    // Get settings from Drupal
    const biolandSettings = settings.bioland || {};
    
    // Only proceed if auto summary is enabled
    if (biolandSettings.enableAutoSummary === false) {
      logger().log('Auto summary is disabled in settings');
      return;
    }

    logger().log('Auto summary enabled, initializing...');
    
    // Initialize auto summary functionality
    initializeAutoSummary(context, biolandSettings);
  }
};

/**
 * Initialize auto summary functionality.
 *
 * @param {Element} context - The context element
 * @param {Object} settings - Bioland settings from PHP
 */
function initializeAutoSummary(context, settings) {
  const summaryField = getSummaryField(context);
  
  if (!summaryField) {
    logger().log('Summary field not found, cannot enable auto-summary');
    return;
  }

  // Prevent duplicate initialization
  if (summaryField.dataset.biolandAutoSummaryInit) {
    logger().log('Auto summary already initialized');
    return;
  }
  summaryField.dataset.biolandAutoSummaryInit = 'true';

  logger().log('Summary field found, setting up auto-summary');

  // Initialize user edit tracking on the field itself
  if (!summaryField.dataset.biolandUserEdited) {
    summaryField.dataset.biolandUserEdited = 'false';
  }

  // Set up summary field listener to detect manual edits
  setupSummaryFieldListener(summaryField);

  // Try to detect and setup the editor
  // Priority: CKEditor 4 > CKEditor 5 > Contenteditable Fallback > Plain Textarea
  if (typeof CKEDITOR !== 'undefined') {
    logger().log('CKEditor 4 detected, attempting to connect...');
    setupCKEditor4(summaryField, context);
  } else if (typeof Drupal.CKEditor5Instances !== 'undefined') {
    logger().log('CKEditor 5 detected, attempting to connect...');
    setupCKEditor5(summaryField, context);
  } else if (document.querySelector('.ck-editor__editable[contenteditable="true"]')) {
    logger().log('CKEditor detected via contenteditable div, using direct monitor...');
    if (!setupContentEditableMonitor(summaryField, context)) {
      setupPlainTextarea(summaryField, context);
    }
  } else {
    logger().log('No CKEditor detected, using plain textarea');
    setupPlainTextarea(summaryField, context);
  }
}

/**
 * Get the summary field element.
 * @param {Element} context - The context element
 * @returns {Element|null} The summary field element or null if not found
 */
function getSummaryField(context) {
  // Try multiple possible selectors - use document instead of context
  const selectors = [
    'textarea[data-drupal-selector="edit-body-0-summary"]',
    '#edit-body-0-summary',
    'textarea[name="body[0][summary]"]'
  ];

  for (const selector of selectors) {
    const field = document.querySelector(selector);
    if (field) {
      logger().log('Found summary field with selector:', selector);
      return field;
    }
  }

  return null;
}

/**
 * Setup listener on summary field to detect manual edits.
 * @param {Element} summaryField - The summary field element
 */
function setupSummaryFieldListener(summaryField) {
  // Check if listener already attached
  if (summaryField.dataset.biolandSummaryListenerInit) {
    return;
  }
  summaryField.dataset.biolandSummaryListenerInit = 'true';

  // Detect manual edits using native event listeners
  summaryField.addEventListener('input', () => {
    logger().log('User manually edited summary, disabling auto-summary');
    summaryField.dataset.biolandUserEdited = 'true';
  });

  summaryField.addEventListener('keyup', () => {
    logger().log('User manually edited summary, disabling auto-summary');
    summaryField.dataset.biolandUserEdited = 'true';
  });
}

/**
 * Setup CKEditor 4 integration.
 * @param {Element} summaryField - The summary field element
 * @param {Element} context - The context element
 */
function setupCKEditor4(summaryField, context) {
  let attempts = 0;
  const maxAttempts = 50; // Try for 5 seconds

  const checkEditor = setInterval(function() {
    attempts++;
    
    // Try multiple possible instance names
    const possibleNames = [
      'edit-body-0-value',
      'body-0-value',
      'edit_body_0_value',
      'body_0_value'
    ];

    let editorInstance = null;
    let foundName = null;

    for (const name of possibleNames) {
      if (CKEDITOR.instances && CKEDITOR.instances[name]) {
        editorInstance = CKEDITOR.instances[name];
        foundName = name;
        break;
      }
    }

    if (editorInstance) {
      clearInterval(checkEditor);
      logger().log('CKEditor 4 instance found:', foundName);

      // Listen for content changes
      editorInstance.on('change', function() {
        if (summaryField.dataset.biolandUserEdited === 'true') return;
        const bodyHtml = editorInstance.getData();
        updateSummaryFromHtml(bodyHtml, summaryField);
      });

      // Also trigger on keyup for immediate feedback (debounced)
      let keyTimeout;
      editorInstance.on('key', function() {
        if (summaryField.dataset.biolandUserEdited === 'true') return;
        clearTimeout(keyTimeout);
        keyTimeout = setTimeout(function() {
          const bodyHtml = editorInstance.getData();
          updateSummaryFromHtml(bodyHtml, summaryField);
        }, 300);
      });

      // Trigger initial update if body has content
      const initialContent = editorInstance.getData();
      if (initialContent && !summaryField.value.trim()) {
        updateSummaryFromHtml(initialContent, summaryField);
      }

      logger().log('CKEditor 4 auto-summary fully initialized');
    } else if (attempts >= maxAttempts) {
      clearInterval(checkEditor);
      logger().warn('CKEditor 4 instance not found after', attempts, 'attempts, falling back to textarea');
      setupPlainTextarea(summaryField, context);
    }
  }, 100);
}

/**
 * Setup CKEditor 5 integration.
 * @param {Element} summaryField - The summary field element
 * @param {Element} context - The context element
 */
function setupCKEditor5(summaryField, context) {
  let attempts = 0;
  const maxAttempts = 50;

  const checkEditor = setInterval(function() {
    attempts++;

    const instances = Drupal.CKEditor5Instances;
    
    // Try to find the body field element first - use document instead of context
    const bodySelectors = [
      '[data-drupal-selector="edit-body-0-value"]',
      '#edit-body-0-value',
      'textarea[name="body[0][value]"]'
    ];

    let bodyFieldId = null;
    let bodyField = null;
    
    for (const selector of bodySelectors) {
      bodyField = document.querySelector(selector);
      if (bodyField) {
        bodyFieldId = bodyField.getAttribute('id');
        logger().log('Found body field element with selector:', selector, 'ID:', bodyFieldId);
        if (bodyFieldId) break;
      }
    }

    // Also check if instances exist and log available instances for debugging
    if (instances) {
      logger().log('CKEditor5Instances available, size:', instances.size);
      if (attempts === 1 && instances.size > 0) {
        // Log available instance IDs on first attempt
        const instanceIds = Array.from(instances.keys());
        logger().log('Available CKEditor 5 instance IDs:', instanceIds);
      }
    } else {
      logger().log('Drupal.CKEditor5Instances is not available');
    }

    // Try to find the editor instance - first by exact ID, then by iterating all instances
    let editorInstance = null;
    if (bodyFieldId && instances && instances.has(bodyFieldId)) {
      editorInstance = instances.get(bodyFieldId);
      logger().log('Found CKEditor 5 instance by exact ID match');
    } else if (instances && instances.size > 0) {
      // Try to find it by checking all instances
      for (const [key, instance] of instances.entries()) {
        logger().log('Checking instance with key:', key);
        // Check if this instance's source element matches our body field
        if (instance.sourceElement && bodyField) {
          if (instance.sourceElement === bodyField || 
              instance.sourceElement.id === bodyFieldId ||
              instance.sourceElement.getAttribute('data-drupal-selector') === bodyField.getAttribute('data-drupal-selector')) {
            editorInstance = instance;
            logger().log('Found CKEditor 5 instance by matching source element with key:', key);
            break;
          }
        }
      }
    }

    if (editorInstance) {
      clearInterval(checkEditor);
      logger().log('CKEditor 5 instance connected successfully');

      // Listen for content changes
      editorInstance.model.document.on('change:data', function() {
        if (summaryField.dataset.biolandUserEdited === 'true') return;
        const bodyHtml = editorInstance.getData();
        updateSummaryFromHtml(bodyHtml, summaryField);
      });

      // Trigger initial update
      const initialContent = editorInstance.getData();
      if (initialContent && !summaryField.value.trim()) {
        updateSummaryFromHtml(initialContent, summaryField);
      }

      logger().log('CKEditor 5 auto-summary fully initialized');
    } else if (attempts >= maxAttempts) {
      clearInterval(checkEditor);
      logger().warn('CKEditor 5 instance not found after', attempts, 'attempts');
      logger().warn('bodyFieldId:', bodyFieldId, 'instances:', instances);
      
      // Try to find the contenteditable div as a fallback
      logger().log('Attempting contenteditable div fallback...');
      if (setupContentEditableMonitor(summaryField, context)) {
        logger().log('Contenteditable monitor setup successful');
      } else {
        logger().warn('Contenteditable monitor failed, falling back to textarea');
        setupPlainTextarea(summaryField, context);
      }
    }
  }, 100);
}

/**
 * Setup direct monitoring of contenteditable div (CKEditor 5 fallback).
 * @param {Element} summaryField - The summary field element
 * @param {Element} context - The context element
 * @returns {boolean} True if successfully setup, false otherwise
 */
function setupContentEditableMonitor(summaryField, context) {
  // Look for the CKEditor contenteditable div
  const editableDiv = document.querySelector('.ck-editor__editable[contenteditable="true"]');
  
  if (!editableDiv) {
    logger().warn('Contenteditable div not found');
    return false;
  }

  // Prevent duplicate initialization
  if (editableDiv.dataset.biolandAutoSummaryInit) {
    logger().log('Contenteditable monitor already initialized');
    return true;
  }
  editableDiv.dataset.biolandAutoSummaryInit = 'true';

  logger().log('Found contenteditable div, setting up monitor');

  // Use MutationObserver to watch for content changes
  const observer = new MutationObserver(function(mutations) {
    if (summaryField.dataset.biolandUserEdited === 'true') return;
    
    // Debounce the updates
    clearTimeout(contentEditableTimeout);
    contentEditableTimeout = setTimeout(function() {
      const htmlContent = editableDiv.innerHTML;
      updateSummaryFromHtml(htmlContent, summaryField);
    }, 300);
  });

  // Observe changes to the contenteditable div
  observer.observe(editableDiv, {
    childList: true,
    subtree: true,
    characterData: true,
    characterDataOldValue: false
  });

  // Also listen for input events as backup
  editableDiv.addEventListener('input', () => {
    if (summaryField.dataset.biolandUserEdited === 'true') return;

    clearTimeout(contentEditableTimeout);
    contentEditableTimeout = setTimeout(() => {
      const htmlContent = editableDiv.innerHTML;
      updateSummaryFromHtml(htmlContent, summaryField);
    }, 300);
  });

  // Trigger initial update
  const initialContent = editableDiv.innerHTML;
  if (initialContent && !summaryField.value.trim()) {
    updateSummaryFromHtml(initialContent, summaryField);
  }

  logger().log('Contenteditable monitor fully initialized');
  return true;
}

/**
 * Setup plain textarea integration (fallback).
 * @param {Element} summaryField - The summary field element
 * @param {Element} context - The context element
 */
function setupPlainTextarea(summaryField, context) {
  const bodySelectors = [
    'textarea[data-drupal-selector="edit-body-0-value"]',
    '#edit-body-0-value',
    'textarea[name="body[0][value]"]'
  ];

  let bodyField = null;
  for (const selector of bodySelectors) {
    bodyField = document.querySelector(selector);
    if (bodyField) {
      logger().log('Found body textarea with selector:', selector);
      break;
    }
  }

  if (!bodyField) {
    logger().warn('Body field not found, cannot enable auto-summary');
    return;
  }

  // Prevent duplicate event binding
  if (bodyField.dataset.biolandAutoSummaryBodyInit) {
    logger().log('Textarea listeners already attached');
    return;
  }
  bodyField.dataset.biolandAutoSummaryBodyInit = 'true';

  logger().log('Setting up textarea auto-summary');

  // Listen for input with debouncing using native event listeners
  let inputTimeout;
  const handleBodyInput = () => {
    if (summaryField.dataset.biolandUserEdited === 'true') return;
    clearTimeout(inputTimeout);
    inputTimeout = setTimeout(() => {
      updateSummaryFromHtml(bodyField.value, summaryField);
    }, 300);
  };

  bodyField.addEventListener('input', handleBodyInput);
  bodyField.addEventListener('keyup', handleBodyInput);

  // Trigger initial update
  if (bodyField.value && !summaryField.value.trim()) {
    updateSummaryFromHtml(bodyField.value, summaryField);
  }

  logger().log('Plain textarea auto-summary fully initialized');
}

/**
 * Update summary field from HTML content.
 * @param {string} bodyHtml - The HTML content from body field
 * @param {Element} summaryField - The summary field element
 */
function updateSummaryFromHtml(bodyHtml, summaryField) {
  // Check if user has edited the summary
  if (!bodyHtml || summaryField.dataset.biolandUserEdited === 'true') {
    logger().log('Skipping update - bodyHtml empty or user edited');
    return;
  }

  try {
    const locale = document.querySelector('html').getAttribute('lang') || 'en';
    
    // Strip HTML with error handling for very long text
    const strippedText = stripHtml(bodyHtml);
    
    if (!strippedText) {
      logger().log('No text content after stripping HTML');
      return;
    }
    
    logger().log('Stripped text length:', strippedText.length, 'First 100 chars:', strippedText.substring(0, 100));
    
    const newSummary = smartTruncate(strippedText, 255, locale);
    logger().log('New summary length:', newSummary.length, 'Content:', newSummary.substring(0, 100));
    
    // Only update if summary is empty or content has changed
    if (summaryField.value.trim() === '' || summaryField.value !== newSummary) {
      summaryField.value = newSummary;
      logger().log('Auto-summary updated:', newSummary.substring(0, 50) + (newSummary.length > 50 ? '...' : ''));
    } else {
      logger().log('Summary unchanged, skipping update');
    }
  } catch (error) {
    logger().error('Error updating summary from HTML:', error);
    // Don't fail silently - at least set a basic summary
    try {
      const basicText = stripHtml(bodyHtml);
      if (basicText && basicText.length > 0) {
        summaryField.value = basicText.substring(0, 255).trim();
      }
    } catch (fallbackError) {
      logger().error('Fallback summary generation also failed:', fallbackError);
    }
  }
}

/**
 * Smart truncate text to specified length, preserving sentence boundaries.
 * @param {string} texts - The text to truncate
 * @param {number} length - Maximum length (default 512)
 * @param {string} locale - Locale for sentence segmentation (default 'en')
 * @returns {string} Truncated text
 */
function smartTruncate(texts, length = 512, locale = 'en') {
  if (!texts) return '';
  
  try {
    const text = stripHtml(texts);
    
    if (!text) return '';
    
    // For very long text (> 50KB), pre-truncate before sentence segmentation
    // to avoid performance issues with Intl.Segmenter
    const maxProcessingLength = 50000; // 50KB limit for segmentation
    const workingText = text.length > maxProcessingLength 
      ? text.substring(0, maxProcessingLength) 
      : text;
    
    // Fallback for browsers that don't support Intl.Segmenter
    if (typeof Intl === 'undefined' || typeof Intl.Segmenter === 'undefined') {
      logger().log('Intl.Segmenter not available, using simple truncation');
      return simpleSmartTruncate(workingText, length);
    }

    const segmenter = new Intl.Segmenter(locale, { granularity: 'sentence' });
    const segments = Array.from(segmenter.segment(workingText), s => s.segment);

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
    logger().warn('smartTruncate error, falling back to simple truncation:', error);
    // More robust fallback - ensure we have text to work with
    try {
      const text = stripHtml(texts);
      return simpleSmartTruncate(text || '', length);
    } catch (fallbackError) {
      logger().error('Even simple truncation failed:', fallbackError);
      return texts ? texts.substring(0, length) : '';
    }
  }
}

/**
 * Simple smart truncate fallback for older browsers.
 * @param {string} text - The text to truncate
 * @param {number} length - Maximum length
 * @returns {string} Truncated text
 */
function simpleSmartTruncate(text, length) {
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
}

/**
 * Strip all HTML tags from a string and clean up whitespace.
 * @param {string} input - Input string
 * @returns {string} String with HTML tags removed and whitespace normalized
 */
function stripHtml(input) {
  if (!input) return '';
  
  try {
    // For very long strings (> 100KB), use a more memory-efficient approach
    const maxDirectParseLength = 100000; // 100KB limit for direct DOM parsing
    
    if (input.length > maxDirectParseLength) {
      logger().log('Large HTML content detected (' + input.length + ' chars), using chunked processing');
      
      // For very long content, use regex-based stripping first to reduce size
      // Remove script and style tags and their contents
      let text = input.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, ' ');
      text = text.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, ' ');
      
      // Remove HTML tags
      text = text.replace(/<[^>]+>/g, ' ');
      
      // Decode common HTML entities
      text = text.replace(/&nbsp;/g, ' ')
                 .replace(/&amp;/g, '&')
                 .replace(/&lt;/g, '<')
                 .replace(/&gt;/g, '>')
                 .replace(/&quot;/g, '"')
                 .replace(/&#39;/g, "'")
                 .replace(/&mdash;/g, '—')
                 .replace(/&ndash;/g, '–');
      
      // Normalize whitespace
      text = text.replace(/\s+/g, ' ').trim();
      
      return text;
    }
    
    // For normal-sized content, use DOM parsing for accuracy
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = input;
    
    // Get the text content (this automatically handles HTML entities)
    let text = tempDiv.textContent || tempDiv.innerText || '';
    
    // Normalize whitespace: replace multiple spaces/newlines with single space
    text = text.replace(/\s+/g, ' ').trim();
    
    return text;
  } catch (error) {
    logger().error('Error in stripHtml:', error);
    
    // Ultra-safe fallback: just use regex to strip tags
    try {
      let text = input.replace(/<[^>]+>/g, ' ');
      text = text.replace(/\s+/g, ' ').trim();
      return text;
    } catch (fallbackError) {
      logger().error('Even fallback stripHtml failed:', fallbackError);
      return '';
    }
  }
}
