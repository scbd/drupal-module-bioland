/**
 * @file
 * Debug logging utility for Bioland module.
 * Provides conditional logging based on settings.
 * 
 * This file MUST be loaded first by Drupal (via library dependency).
 * Other Bioland JS files call window.biolandGetLogger(area, settings) to get a logger.
 * 
 * Note: Only uses const (no let/var) per project conventions.
 */

(function(window) {
  'use strict';

  /**
   * Create a noop logger for when logging is disabled.
   * 
   * @returns {Object} Logger object with no-op methods
   */
  const createNoopLogger = function() {
    const noop = function() {};
    return {
      log: noop,
      warn: noop,
      error: noop
    };
  };

  /**
   * Create an active logger for a specific area.
   * 
   * @param {string} area - The area name for prefixing logs
   * @returns {Object} Logger object with console methods
   */
  const createActiveLogger = function(area) {
    const prefix = 'Bioland [' + area + ']: ';
    return {
      log: function() {
        const args = Array.prototype.slice.call(arguments);
        args[0] = prefix + (args[0] || '');
        console.log.apply(console, args);
      },
      warn: function() {
        const args = Array.prototype.slice.call(arguments);
        args[0] = prefix + (args[0] || '');
        console.warn.apply(console, args);
      },
      error: function() {
        const args = Array.prototype.slice.call(arguments);
        args[0] = prefix + (args[0] || '');
        console.error.apply(console, args);
      }
    };
  };

  /**
   * Get a logger for a specific area based on settings.
   * 
   * Usage: const logger = window.biolandGetLogger('autoSummary', settings.bioland);
   * 
   * @param {string} area - The area name (fieldVisibility, additionalFields, autoSummary, helpComments, settingsToggle)
   * @param {Object} settings - The bioland settings object from drupalSettings.bioland
   * @returns {Object} Logger object with log, warn, error methods
   */
  window.biolandGetLogger = function(area, settings) {
    const biolandSettings = settings || {};
    const enableDebugLogging = biolandSettings.enableDebugLogging || false;
    const debugLogAreas = biolandSettings.debugLogAreas || {};

    if (!enableDebugLogging) {
      return createNoopLogger();
    }

    if (debugLogAreas[area] === false) {
      return createNoopLogger();
    }

    return createActiveLogger(area);
  };

})(window);
