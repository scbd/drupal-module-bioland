/**
 * @file
 * Jest setup file for Bioland module tests.
 * Sets up the global Drupal object and other mocks needed for testing.
 */

// Mock the global Drupal object
global.Drupal = {
  behaviors: {},
  t: (str) => str, // Simple translation mock that returns the string
  // Note: CKEditor5Instances is intentionally NOT set here - let tests set it as needed
};

// Mock drupalSettings for debug logging
global.drupalSettings = {
  bioland: {
    enableDebugLogging: true,
    debugLogAreas: {
      fieldVisibility: true,
      additionalFields: true,
      autoSummary: true,
      helpComments: true,
      settingsToggle: true
    }
  }
};

/**
 * Mock window.biolandGetLogger - the new logger factory pattern.
 * This is used by all Bioland JS files to get a logger instance.
 * 
 * @param {string} area - The area name for prefixing logs
 * @param {Object} settings - The bioland settings object
 * @returns {Object} Logger object with log, warn, error methods
 */
global.window.biolandGetLogger = function(area, settings) {
  const biolandSettings = settings || {};
  const enableDebugLogging = biolandSettings.enableDebugLogging !== false;
  const debugLogAreas = biolandSettings.debugLogAreas || {};

  if (!enableDebugLogging || debugLogAreas[area] === false) {
    return {
      log: function() {},
      warn: function() {},
      error: function() {}
    };
  }

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

// Legacy BiolandLogger mock (kept for backwards compatibility with older test patterns)
global.BiolandLogger = {
  isLoggingEnabled: function() { return true; },
  log: function(area, message) { console.log.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 2))); },
  warn: function(area, message) { console.warn.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 2))); },
  error: function(area, message) { console.error.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 2))); },
  fieldVisibility: {
    log: function(message) { console.log.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    warn: function(message) { console.warn.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    error: function(message) { console.error.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); }
  },
  additionalFields: {
    log: function(message) { console.log.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    warn: function(message) { console.warn.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    error: function(message) { console.error.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); }
  },
  autoSummary: {
    log: function(message) { console.log.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    warn: function(message) { console.warn.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    error: function(message) { console.error.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); }
  },
  helpComments: {
    log: function(message) { console.log.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    warn: function(message) { console.warn.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); },
    error: function(message) { console.error.apply(console, ['Bioland: ' + message].concat(Array.prototype.slice.call(arguments, 1))); }
  }
};

// Mock console methods to reduce noise in tests (optional)
// Comment out if you want to see console output during tests
// global.console = {
//   ...console,
//   log: jest.fn(),
//   warn: jest.fn(),
//   error: jest.fn(),
// };

// Reset DOM between tests
beforeEach(() => {
  document.body.innerHTML = '';
  document.head.innerHTML = '';
  
  // Reset Drupal behaviors and CKEditor5Instances
  global.Drupal.behaviors = {};
  delete global.Drupal.CKEditor5Instances;
  
  // Reset cookies
  document.cookie.split(';').forEach((c) => {
    document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
  });
});

// Clean up after each test
afterEach(() => {
  jest.clearAllMocks();
  jest.clearAllTimers();
});
