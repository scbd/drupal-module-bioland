/**
 * @file
 * Unit tests for bioland-debug-logger-1-0-37.js
 */

describe('Bioland Debug Logger', () => {
  let originalConsoleLog;
  let originalConsoleWarn;
  let originalConsoleError;

  beforeEach(() => {
    // Suppress console output during tests
    originalConsoleLog = console.log;
    originalConsoleWarn = console.warn;
    originalConsoleError = console.error;
    console.log = jest.fn();
    console.warn = jest.fn();
    console.error = jest.fn();

    // Clear window.biolandGetLogger if it exists
    delete window.biolandGetLogger;

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
    console.warn = originalConsoleWarn;
    console.error = originalConsoleError;
  });

  describe('Logger initialization', () => {
    test('should expose biolandGetLogger on window object', () => {
      require('./bioland-debug-logger-1-0-37.js');
      expect(window.biolandGetLogger).toBeDefined();
      expect(typeof window.biolandGetLogger).toBe('function');
    });
  });

  describe('Noop logger (debug disabled)', () => {
    test('should return noop logger when enableDebugLogging is false', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: false };
      const logger = window.biolandGetLogger('testArea', settings);
      
      expect(logger).toBeDefined();
      expect(typeof logger.log).toBe('function');
      expect(typeof logger.warn).toBe('function');
      expect(typeof logger.error).toBe('function');
      
      // Call logger methods - they should do nothing
      logger.log('test message');
      logger.warn('test warning');
      logger.error('test error');
      
      expect(console.log).not.toHaveBeenCalled();
      expect(console.warn).not.toHaveBeenCalled();
      expect(console.error).not.toHaveBeenCalled();
    });

    test('should return noop logger when settings is undefined', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const logger = window.biolandGetLogger('testArea', undefined);
      
      logger.log('test message');
      expect(console.log).not.toHaveBeenCalled();
    });

    test('should return noop logger when settings is null', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const logger = window.biolandGetLogger('testArea', null);
      
      logger.log('test message');
      expect(console.log).not.toHaveBeenCalled();
    });

    test('should return noop logger when specific area is disabled', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = {
        enableDebugLogging: true,
        debugLogAreas: {
          testArea: false
        }
      };
      const logger = window.biolandGetLogger('testArea', settings);
      
      logger.log('test message');
      expect(console.log).not.toHaveBeenCalled();
    });
  });

  describe('Active logger (debug enabled)', () => {
    test('should return active logger when enableDebugLogging is true', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('testArea', settings);
      
      logger.log('test message');
      expect(console.log).toHaveBeenCalledWith('Bioland [testArea]: test message');
    });

    test('should prefix log messages with area name', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('autoSummary', settings);
      
      logger.log('initialization complete');
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: initialization complete');
    });

    test('should prefix warn messages with area name', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('fieldVisibility', settings);
      
      logger.warn('field not found');
      expect(console.warn).toHaveBeenCalledWith('Bioland [fieldVisibility]: field not found');
    });

    test('should prefix error messages with area name', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('additionalFields', settings);
      
      logger.error('mount failed');
      expect(console.error).toHaveBeenCalledWith('Bioland [additionalFields]: mount failed');
    });

    test('should handle multiple arguments in log', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.log('value is', 123, 'and', { key: 'value' });
      expect(console.log).toHaveBeenCalledWith('Bioland [test]: value is', 123, 'and', { key: 'value' });
    });

    test('should handle multiple arguments in warn', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.warn('warning:', 'code', 404);
      expect(console.warn).toHaveBeenCalledWith('Bioland [test]: warning:', 'code', 404);
    });

    test('should handle multiple arguments in error', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.error('error:', new Error('test'));
      expect(console.error).toHaveBeenCalledWith('Bioland [test]: error:', new Error('test'));
    });

    test('should handle empty message in log', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.log();
      expect(console.log).toHaveBeenCalledWith('Bioland [test]: ');
    });

    test('should handle empty message in warn', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.warn();
      expect(console.warn).toHaveBeenCalledWith('Bioland [test]: ');
    });

    test('should handle empty message in error', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('test', settings);
      
      logger.error();
      expect(console.error).toHaveBeenCalledWith('Bioland [test]: ');
    });

    test('should respect area-specific settings when area is enabled', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = {
        enableDebugLogging: true,
        debugLogAreas: {
          enabledArea: true
        }
      };
      const logger = window.biolandGetLogger('enabledArea', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [enabledArea]: test');
    });

    test('should work when debugLogAreas is undefined', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = {
        enableDebugLogging: true
      };
      const logger = window.biolandGetLogger('testArea', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [testArea]: test');
    });

    test('should work when area is not in debugLogAreas', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = {
        enableDebugLogging: true,
        debugLogAreas: {
          otherArea: true
        }
      };
      const logger = window.biolandGetLogger('testArea', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [testArea]: test');
    });
  });

  describe('Different area names', () => {
    test('should create logger for fieldVisibility area', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('fieldVisibility', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: test');
    });

    test('should create logger for additionalFields area', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('additionalFields', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: test');
    });

    test('should create logger for autoSummary area', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('autoSummary', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: test');
    });

    test('should create logger for helpComments area', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('helpComments', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: test');
    });

    test('should create logger for settingsToggle area', () => {
      require('./bioland-debug-logger-1-0-37.js');
      
      const settings = { enableDebugLogging: true };
      const logger = window.biolandGetLogger('settingsToggle', settings);
      
      logger.log('test');
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: test');
    });
  });
});
