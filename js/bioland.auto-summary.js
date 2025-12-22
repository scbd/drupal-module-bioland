/**
 * @file
 * Auto summary functionality for Bioland module.
 * Automatically generates summary text from body field content.
 * Supports CKEditor 4, CKEditor 5, and plain textareas.
 * 
 * Note: No module-level state. All state passed via parameters or stored in DOM data attributes.
 * Uses window.biolandGetLogger for logging (loaded via debug_logger dependency).
 */
(function(Drupal, window, document) {
  'use strict';

  /**
   * Get the summary field element.
   * 
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   * @returns {Element|null} The summary field element or null if not found
   */
  const getSummaryField = function(context, logger) {
    const selectors = [
      'textarea[data-drupal-selector="edit-body-0-summary"]',
      '#edit-body-0-summary',
      'textarea[name="body[0][summary]"]'
    ];

    for (const selector of selectors) {
      const field = document.querySelector(selector);
      if (field) {
        logger.log('Found summary field with selector:', selector);
        return field;
      }
    }

    return null;
  };

  /**
   * Setup listener on summary field to detect manual edits.
   * 
   * @param {Element} summaryField - The summary field element
   * @param {Object} logger - Logger instance
   */
  const setupSummaryFieldListener = function(summaryField, logger) {
    if (summaryField.dataset.biolandSummaryListenerInit) {
      return;
    }
    summaryField.dataset.biolandSummaryListenerInit = 'true';

    summaryField.addEventListener('input', function() {
      logger.log('User manually edited summary, disabling auto-summary');
      summaryField.dataset.biolandUserEdited = 'true';
    });

    summaryField.addEventListener('keyup', function() {
      logger.log('User manually edited summary, disabling auto-summary');
      summaryField.dataset.biolandUserEdited = 'true';
    });
  };

  /**
   * Strip all HTML tags from a string and clean up whitespace.
   * 
   * @param {string} input - Input string
   * @param {Object} logger - Logger instance
   * @returns {string} String with HTML tags removed and whitespace normalized
   */
  const stripHtml = function(input, logger) {
    if (!input) return '';
    
    try {
      const maxDirectParseLength = 100000;
      
      if (input.length > maxDirectParseLength) {
        logger.log('Large HTML content detected (' + input.length + ' chars), using chunked processing');
        
        const text = input
          .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, ' ')
          .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, ' ')
          .replace(/<[^>]+>/g, ' ')
          .replace(/&nbsp;/g, ' ')
          .replace(/&amp;/g, '&')
          .replace(/&lt;/g, '<')
          .replace(/&gt;/g, '>')
          .replace(/&quot;/g, '"')
          .replace(/&#39;/g, "'")
          .replace(/&mdash;/g, '—')
          .replace(/&ndash;/g, '–')
          .replace(/\s+/g, ' ')
          .trim();
        
        return text;
      }
      
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = input;
      
      const text = (tempDiv.textContent || tempDiv.innerText || '').replace(/\s+/g, ' ').trim();
      
      return text;
    } catch (error) {
      logger.error('Error in stripHtml:', error);
      
      try {
        const text = input.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        return text;
      } catch (fallbackError) {
        logger.error('Even fallback stripHtml failed:', fallbackError);
        return '';
      }
    }
  };

  /**
   * Simple smart truncate fallback for older browsers.
   * 
   * @param {string} text - The text to truncate
   * @param {number} length - Maximum length
   * @returns {string} Truncated text
   */
  const simpleSmartTruncate = function(text, length) {
    if (text.length <= length) return text;
    
    const truncated = text.substring(0, length);
    const lastPeriod = truncated.lastIndexOf('.');
    const lastSpace = truncated.lastIndexOf(' ');
    
    if (lastPeriod > length - 50) {
      return truncated.substring(0, lastPeriod + 1);
    } else if (lastSpace > length - 50) {
      return truncated.substring(0, lastSpace) + '...';
    } else {
      return truncated + '...';
    }
  };

  /**
   * Smart truncate text to specified length, preserving sentence boundaries.
   * 
   * @param {string} texts - The text to truncate
   * @param {number} length - Maximum length (default 512)
   * @param {string} locale - Locale for sentence segmentation (default 'en')
   * @param {Object} logger - Logger instance
   * @returns {string} Truncated text
   */
  const smartTruncate = function(texts, length, locale, logger) {
    const maxLength = length || 512;
    const textLocale = locale || 'en';
    
    if (!texts) return '';
    
    try {
      const text = stripHtml(texts, logger);
      
      if (!text) return '';
      
      const maxProcessingLength = 50000;
      const workingText = text.length > maxProcessingLength 
        ? text.substring(0, maxProcessingLength) 
        : text;
      
      if (typeof Intl === 'undefined' || typeof Intl.Segmenter === 'undefined') {
        logger.log('Intl.Segmenter not available, using simple truncation');
        return simpleSmartTruncate(workingText, maxLength);
      }

      const segmenter = new Intl.Segmenter(textLocale, { granularity: 'sentence' });
      const segments = Array.from(segmenter.segment(workingText), function(s) { return s.segment; });

      const sentences = [];
      const state = { charCount: 0 };

      for (const segment of segments) {
        if (state.charCount + segment.length > maxLength) break;
        sentences.push(segment);
        state.charCount += segment.length;
      }

      const result = Array.isArray(sentences.join('')) ? sentences.join('')[0] : sentences.join('');
      const response = result.length > maxLength ? result.substring(0, maxLength) : result;
      const lastIndexOf = response.includes('.') ? response.lastIndexOf('.') + 1 : response.length;

      return response.substring(0, lastIndexOf);
    } catch (error) {
      logger.warn('smartTruncate error, falling back to simple truncation:', error);
      try {
        const text = stripHtml(texts, logger);
        return simpleSmartTruncate(text || '', maxLength);
      } catch (fallbackError) {
        logger.error('Even simple truncation failed:', fallbackError);
        return texts ? texts.substring(0, maxLength) : '';
      }
    }
  };

  /**
   * Update summary field from HTML content.
   * 
   * @param {string} bodyHtml - The HTML content from body field
   * @param {Element} summaryField - The summary field element
   * @param {Object} logger - Logger instance
   */
  const updateSummaryFromHtml = function(bodyHtml, summaryField, logger) {
    if (!bodyHtml || summaryField.dataset.biolandUserEdited === 'true') {
      logger.log('Skipping update - bodyHtml empty or user edited');
      return;
    }

    try {
      const locale = document.querySelector('html').getAttribute('lang') || 'en';
      const strippedText = stripHtml(bodyHtml, logger);
      
      if (!strippedText) {
        logger.log('No text content after stripping HTML');
        return;
      }
      
      logger.log('Stripped text length:', strippedText.length, 'First 100 chars:', strippedText.substring(0, 100));
      
      const newSummary = smartTruncate(strippedText, 255, locale, logger);
      logger.log('New summary length:', newSummary.length, 'Content:', newSummary.substring(0, 100));
      
      if (summaryField.value.trim() === '' || summaryField.value !== newSummary) {
        summaryField.value = newSummary;
        logger.log('Auto-summary updated:', newSummary.substring(0, 50) + (newSummary.length > 50 ? '...' : ''));
      } else {
        logger.log('Summary unchanged, skipping update');
      }
    } catch (error) {
      logger.error('Error updating summary from HTML:', error);
      try {
        const basicText = stripHtml(bodyHtml, logger);
        if (basicText && basicText.length > 0) {
          summaryField.value = basicText.substring(0, 255).trim();
        }
      } catch (fallbackError) {
        logger.error('Fallback summary generation also failed:', fallbackError);
      }
    }
  };

  /**
   * Find body textarea element.
   * 
   * @param {Array} selectors - Array of selectors to try
   * @param {Object} logger - Logger instance
   * @returns {Element|null} The textarea element or null
   */
  const findBodyTextarea = function(selectors, logger) {
    for (const selector of selectors) {
      const bodyField = document.querySelector(selector);
      if (bodyField) {
        logger.log('Found body textarea with selector:', selector);
        return bodyField;
      }
    }
    return null;
  };

  /**
   * Setup plain textarea integration (fallback).
   * 
   * @param {Element} summaryField - The summary field element
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   */
  const setupPlainTextarea = function(summaryField, context, logger) {
    const bodySelectors = [
      'textarea[data-drupal-selector="edit-body-0-value"]',
      '#edit-body-0-value',
      'textarea[name="body[0][value]"]'
    ];

    const bodyField = findBodyTextarea(bodySelectors, logger);

    if (!bodyField) {
      logger.warn('Body field not found, cannot enable auto-summary');
      return;
    }

    if (bodyField.dataset.biolandAutoSummaryBodyInit) {
      logger.log('Textarea listeners already attached');
      return;
    }
    bodyField.dataset.biolandAutoSummaryBodyInit = 'true';

    logger.log('Setting up textarea auto-summary');

    const handleBodyInput = function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;
      const existingTimeout = summaryField.dataset.biolandInputTimeout;
      if (existingTimeout) {
        clearTimeout(parseInt(existingTimeout, 10));
      }
      const timeoutId = setTimeout(function() {
        updateSummaryFromHtml(bodyField.value, summaryField, logger);
      }, 300);
      summaryField.dataset.biolandInputTimeout = String(timeoutId);
    };

    bodyField.addEventListener('input', handleBodyInput);
    bodyField.addEventListener('keyup', handleBodyInput);

    if (bodyField.value && !summaryField.value.trim()) {
      updateSummaryFromHtml(bodyField.value, summaryField, logger);
    }

    logger.log('Plain textarea auto-summary fully initialized');
  };

  /**
   * Setup direct monitoring of contenteditable div (CKEditor 5 fallback).
   * 
   * @param {Element} summaryField - The summary field element
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   * @returns {boolean} True if successfully setup, false otherwise
   */
  const setupContentEditableMonitor = function(summaryField, context, logger) {
    const editableDiv = document.querySelector('.ck-editor__editable[contenteditable="true"]');
    
    if (!editableDiv) {
      logger.warn('Contenteditable div not found');
      return false;
    }

    if (editableDiv.dataset.biolandAutoSummaryInit) {
      logger.log('Contenteditable monitor already initialized');
      return true;
    }
    editableDiv.dataset.biolandAutoSummaryInit = 'true';

    logger.log('Found contenteditable div, setting up monitor');

    const observer = new MutationObserver(function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;
      
      const existingTimeout = summaryField.dataset.biolandContentTimeout;
      if (existingTimeout) {
        clearTimeout(parseInt(existingTimeout, 10));
      }
      const timeoutId = setTimeout(function() {
        const htmlContent = editableDiv.innerHTML;
        updateSummaryFromHtml(htmlContent, summaryField, logger);
      }, 300);
      summaryField.dataset.biolandContentTimeout = String(timeoutId);
    });

    observer.observe(editableDiv, {
      childList: true,
      subtree: true,
      characterData: true,
      characterDataOldValue: false
    });

    editableDiv.addEventListener('input', function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;

      const existingTimeout = summaryField.dataset.biolandContentTimeout;
      if (existingTimeout) {
        clearTimeout(parseInt(existingTimeout, 10));
      }
      const timeoutId = setTimeout(function() {
        const htmlContent = editableDiv.innerHTML;
        updateSummaryFromHtml(htmlContent, summaryField, logger);
      }, 300);
      summaryField.dataset.biolandContentTimeout = String(timeoutId);
    });

    const initialContent = editableDiv.innerHTML;
    if (initialContent && !summaryField.value.trim()) {
      updateSummaryFromHtml(initialContent, summaryField, logger);
    }

    logger.log('Contenteditable monitor fully initialized');
    return true;
  };

  /**
   * Find the body field element.
   * 
   * @param {Object} logger - Logger instance
   * @returns {Element|null} The body field element or null
   */
  const findBodyField = function(logger) {
    const bodySelectors = [
      '[data-drupal-selector="edit-body-0-value"]',
      '#edit-body-0-value',
      'textarea[name="body[0][value]"]'
    ];

    for (const selector of bodySelectors) {
      const bodyField = document.querySelector(selector);
      if (bodyField) {
        logger.log('Found body field element with selector:', selector, 'ID:', bodyField.getAttribute('id'));
        return bodyField;
      }
    }
    return null;
  };

  /**
   * Find CKEditor 5 instance.
   * 
   * @param {Map} instances - CKEditor5Instances map
   * @param {string} bodyFieldId - The body field ID
   * @param {Element} bodyField - The body field element
   * @param {Object} logger - Logger instance
   * @returns {Object|null} CKEditor 5 instance or null
   */
  const findCKEditor5Instance = function(instances, bodyFieldId, bodyField, logger) {
    if (bodyFieldId && instances && instances.has(bodyFieldId)) {
      logger.log('Found CKEditor 5 instance by exact ID match');
      return instances.get(bodyFieldId);
    }
    
    if (instances && instances.size > 0) {
      for (const [key, instance] of instances.entries()) {
        logger.log('Checking instance with key:', key);
        if (instance.sourceElement && bodyField) {
          if (instance.sourceElement === bodyField || 
              instance.sourceElement.id === bodyFieldId ||
              instance.sourceElement.getAttribute('data-drupal-selector') === bodyField.getAttribute('data-drupal-selector')) {
            logger.log('Found CKEditor 5 instance by matching source element with key:', key);
            return instance;
          }
        }
      }
    }
    return null;
  };

  /**
   * Attach listeners to CKEditor 5 instance.
   * 
   * @param {Object} editorInstance - CKEditor 5 instance
   * @param {Element} summaryField - The summary field element
   * @param {Object} logger - Logger instance
   */
  const attachCKEditor5Listeners = function(editorInstance, summaryField, logger) {
    editorInstance.model.document.on('change:data', function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;
      const bodyHtml = editorInstance.getData();
      updateSummaryFromHtml(bodyHtml, summaryField, logger);
    });

    const initialContent = editorInstance.getData();
    if (initialContent && !summaryField.value.trim()) {
      updateSummaryFromHtml(initialContent, summaryField, logger);
    }
  };

  /**
   * Setup CKEditor 5 integration.
   * 
   * @param {Element} summaryField - The summary field element
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   */
  const setupCKEditor5 = function(summaryField, context, logger) {
    const maxAttempts = 50;
    const state = { attempts: 0 };

    const checkEditor = setInterval(function() {
      state.attempts++;

      const instances = Drupal.CKEditor5Instances;
      const bodyField = findBodyField(logger);
      const bodyFieldId = bodyField ? bodyField.getAttribute('id') : null;

      if (instances) {
        logger.log('CKEditor5Instances available, size:', instances.size);
        if (state.attempts === 1 && instances.size > 0) {
          const instanceIds = Array.from(instances.keys());
          logger.log('Available CKEditor 5 instance IDs:', instanceIds);
        }
      } else {
        logger.log('Drupal.CKEditor5Instances is not available');
      }

      const editorInstance = findCKEditor5Instance(instances, bodyFieldId, bodyField, logger);

      if (editorInstance) {
        clearInterval(checkEditor);
        logger.log('CKEditor 5 instance connected successfully');
        attachCKEditor5Listeners(editorInstance, summaryField, logger);
        logger.log('CKEditor 5 auto-summary fully initialized');
      } else if (state.attempts >= maxAttempts) {
        clearInterval(checkEditor);
        logger.warn('CKEditor 5 instance not found after', state.attempts, 'attempts');
        logger.warn('bodyFieldId:', bodyFieldId, 'instances:', instances);
        
        logger.log('Attempting contenteditable div fallback...');
        if (setupContentEditableMonitor(summaryField, context, logger)) {
          logger.log('Contenteditable monitor setup successful');
        } else {
          logger.warn('Contenteditable monitor failed, falling back to textarea');
          setupPlainTextarea(summaryField, context, logger);
        }
      }
    }, 100);
  };

  /**
   * Find CKEditor 4 instance by name.
   * 
   * @param {Array} possibleNames - Array of possible instance names
   * @returns {Object} Object with instance and name properties
   */
  const findCKEditor4Instance = function(possibleNames) {
    for (const name of possibleNames) {
      if (CKEDITOR.instances && CKEDITOR.instances[name]) {
        return { instance: CKEDITOR.instances[name], name: name };
      }
    }
    return { instance: null, name: null };
  };

  /**
   * Attach listeners to CKEditor 4 instance.
   * 
   * @param {Object} editorInstance - CKEditor 4 instance
   * @param {Element} summaryField - The summary field element
   * @param {Object} logger - Logger instance
   */
  const attachCKEditor4Listeners = function(editorInstance, summaryField, logger) {
    editorInstance.on('change', function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;
      const bodyHtml = editorInstance.getData();
      updateSummaryFromHtml(bodyHtml, summaryField, logger);
    });

    // Store timeout ID in DOM for debouncing
    editorInstance.on('key', function() {
      if (summaryField.dataset.biolandUserEdited === 'true') return;
      const existingTimeout = summaryField.dataset.biolandKeyTimeout;
      if (existingTimeout) {
        clearTimeout(parseInt(existingTimeout, 10));
      }
      const timeoutId = setTimeout(function() {
        const bodyHtml = editorInstance.getData();
        updateSummaryFromHtml(bodyHtml, summaryField, logger);
      }, 300);
      summaryField.dataset.biolandKeyTimeout = String(timeoutId);
    });

    const initialContent = editorInstance.getData();
    if (initialContent && !summaryField.value.trim()) {
      updateSummaryFromHtml(initialContent, summaryField, logger);
    }
  };

  /**
   * Setup CKEditor 4 integration.
   * 
   * @param {Element} summaryField - The summary field element
   * @param {Element} context - The context element
   * @param {Object} logger - Logger instance
   */
  const setupCKEditor4 = function(summaryField, context, logger) {
    const maxAttempts = 50;
    const state = { attempts: 0 };

    const checkEditor = setInterval(function() {
      state.attempts++;
      
      const possibleNames = [
        'edit-body-0-value',
        'body-0-value',
        'edit_body_0_value',
        'body_0_value'
      ];

      const found = findCKEditor4Instance(possibleNames);

      if (found.instance) {
        clearInterval(checkEditor);
        logger.log('CKEditor 4 instance found:', found.name);
        attachCKEditor4Listeners(found.instance, summaryField, logger);
        logger.log('CKEditor 4 auto-summary fully initialized');
      } else if (state.attempts >= maxAttempts) {
        clearInterval(checkEditor);
        logger.warn('CKEditor 4 instance not found after', state.attempts, 'attempts, falling back to textarea');
        setupPlainTextarea(summaryField, context, logger);
      }
    }, 100);
  };

  /**
   * Initialize auto summary functionality.
   *
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const initializeAutoSummary = function(context, settings, logger) {
    const summaryField = getSummaryField(context, logger);
    
    if (!summaryField) {
      logger.log('Summary field not found, cannot enable auto-summary');
      return;
    }

    if (summaryField.dataset.biolandAutoSummaryInit) {
      logger.log('Auto summary already initialized');
      return;
    }
    summaryField.dataset.biolandAutoSummaryInit = 'true';

    logger.log('Summary field found, setting up auto-summary');

    if (!summaryField.dataset.biolandUserEdited) {
      summaryField.dataset.biolandUserEdited = 'false';
    }

    setupSummaryFieldListener(summaryField, logger);

    // Priority: CKEditor 4 > CKEditor 5 > Contenteditable Fallback > Plain Textarea
    if (typeof CKEDITOR !== 'undefined') {
      logger.log('CKEditor 4 detected, attempting to connect...');
      setupCKEditor4(summaryField, context, logger);
    } else if (typeof Drupal.CKEditor5Instances !== 'undefined') {
      logger.log('CKEditor 5 detected, attempting to connect...');
      setupCKEditor5(summaryField, context, logger);
    } else if (document.querySelector('.ck-editor__editable[contenteditable="true"]')) {
      logger.log('CKEditor detected via contenteditable div, using direct monitor...');
      if (!setupContentEditableMonitor(summaryField, context, logger)) {
        setupPlainTextarea(summaryField, context, logger);
      }
    } else {
      logger.log('No CKEditor detected, using plain textarea');
      setupPlainTextarea(summaryField, context, logger);
    }
  };

  /**
   * Drupal behavior for Bioland auto summary.
   */
  Drupal.behaviors.biolandAutoSummary = {
    attach: function(context, settings) {
      const biolandSettings = settings.bioland || {};
      const logger = window.biolandGetLogger('autoSummary', biolandSettings);
      
      if (biolandSettings.enableAutoSummary === false) {
        logger.log('Auto summary is disabled in settings');
        return;
      }

      logger.log('Auto summary enabled, initializing...');
      initializeAutoSummary(context, biolandSettings, logger);
    }
  };

})(Drupal, window, document);
