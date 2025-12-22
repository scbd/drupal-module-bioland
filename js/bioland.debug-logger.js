/**
 * @file
 * Debug logging utility for Bioland module.
 * Provides conditional logging based on settings.
 */

/**
 * Bioland debug logger.
 * Only logs when debug logging is enabled in settings.
 */
window.BiolandLogger = (function() {
  'use strict';

  /**
   * Check if logging is enabled for a specific area.
   *
   * @param {string} area - The area to check (fieldVisibility, additionalFields, autoSummary, helpComments)
   * @returns {boolean} True if logging is enabled for this area
   */
  function isLoggingEnabled(area) {
    const settings = Drupal?.settings?.bioland || drupalSettings?.bioland || {};
    
    // Check if debug logging is enabled globally
    if (!settings.enableDebugLogging) {
      return false;
    }

    // Check if specific area logging is enabled
    const debugLogAreas = settings.debugLogAreas || {};
    return debugLogAreas[area] !== false;
  }

  /**
   * Log a message for a specific area.
   *
   * @param {string} area - The area (fieldVisibility, additionalFields, autoSummary, helpComments)
   * @param {string} message - The message to log
   * @param {...*} args - Additional arguments to log
   */
  function log(area, message, ...args) {
    if (isLoggingEnabled(area)) {
      console.log('Bioland: ' + message, ...args);
    }
  }

  /**
   * Log a warning for a specific area.
   *
   * @param {string} area - The area
   * @param {string} message - The message to log
   * @param {...*} args - Additional arguments to log
   */
  function warn(area, message, ...args) {
    if (isLoggingEnabled(area)) {
      console.warn('Bioland: ' + message, ...args);
    }
  }

  /**
   * Log an error for a specific area.
   *
   * @param {string} area - The area
   * @param {string} message - The message to log
   * @param {...*} args - Additional arguments to log
   */
  function error(area, message, ...args) {
    if (isLoggingEnabled(area)) {
      console.error('Bioland: ' + message, ...args);
    }
  }

  // Area-specific loggers for convenience
  return {
    isLoggingEnabled: isLoggingEnabled,
    log: log,
    warn: warn,
    error: error,
    
    // Convenience methods for each area
    fieldVisibility: {
      log: function(message, ...args) { log('fieldVisibility', message, ...args); },
      warn: function(message, ...args) { warn('fieldVisibility', message, ...args); },
      error: function(message, ...args) { error('fieldVisibility', message, ...args); }
    },
    additionalFields: {
      log: function(message, ...args) { log('additionalFields', message, ...args); },
      warn: function(message, ...args) { warn('additionalFields', message, ...args); },
      error: function(message, ...args) { error('additionalFields', message, ...args); }
    },
    autoSummary: {
      log: function(message, ...args) { log('autoSummary', message, ...args); },
      warn: function(message, ...args) { warn('autoSummary', message, ...args); },
      error: function(message, ...args) { error('autoSummary', message, ...args); }
    },
    helpComments: {
      log: function(message, ...args) { log('helpComments', message, ...args); },
      warn: function(message, ...args) { warn('helpComments', message, ...args); },
      error: function(message, ...args) { error('helpComments', message, ...args); }
    }
  };
})();
