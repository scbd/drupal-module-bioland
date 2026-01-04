/**
 * @file
 * Unit tests for bioland-additional-fields-1-0-42.js
 */

describe('Bioland Additional Fields', () => {
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

    // Reset Drupal behaviors
    global.Drupal.behaviors = {};

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
    console.warn = originalConsoleWarn;
    console.error = originalConsoleError;
  });

  describe('Drupal behavior registration', () => {
    test('should register biolandAdditionalFields behavior', () => {
      require('./bioland-additional-fields-1-0-42.js');
      expect(Drupal.behaviors.biolandAdditionalFields).toBeDefined();
      expect(typeof Drupal.behaviors.biolandAdditionalFields.attach).toBe('function');
    });

    test('should not initialize if enableAdditionalFields is false', () => {
      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: false } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Should return early without logging initialization
      expect(console.log).not.toHaveBeenCalledWith('Bioland [additionalFields]: Initializing additional fields');
    });
  });

  describe('Content type field detection', () => {
    test('should detect content type field from select element', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
            <option value="5">Project</option>
          </select>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Initial content type value:', '3');
    });

    test('should handle missing content type field gracefully', () => {
      document.body.innerHTML = '<form class="node-content-form"></form>';

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: No initial content type value found, checking again shortly...');
    });
  });

  describe('shouldMountAdditionalFields', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;
    });

    test('should return true for content type 3 (events)', () => {
      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Content type 3 should have additional fields
      expect(console.log).not.toHaveBeenCalledWith('Bioland [additionalFields]: No additional fields for content type:', '3');
    });

    test('should return true for content type 5 (projects)', () => {
      document.querySelector('#edit-field-type-placement').value = '5';
      
      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).not.toHaveBeenCalledWith('Bioland [additionalFields]: No additional fields for content type:', '5');
    });

    test('should return false for content types without additional fields', () => {
      // Set up DOM with content type 1 which has no additional fields
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="1" selected>Other</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;
      
      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // The value is a string '1' from the select element
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: No additional fields for content type:', '1');
    });
  });

  describe('Vue app mounting', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <html lang="en">
        <body>
          <form class="node-content-form">
            <select id="edit-field-type-placement">
              <option value="3" selected>Event</option>
            </select>
            <div id="edit-field-tags-wrapper"></div>
          </form>
        </body>
        </html>
      `;
    });

    test('should warn when Vue is not available', () => {
      // Ensure Vue is not defined
      global.Vue = undefined;
      global.ScbdDrupalScbdFieldJs = undefined;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.warn).toHaveBeenCalledWith('Bioland [additionalFields]: Vue or ScbdDrupalScbdFieldJs is not available for additional fields');
    });

    test('should create hidden field for additional fields', () => {
      // Mock Vue
      const mockMount = jest.fn();
      const mockApp = { mount: mockMount };
      global.Vue = {
        createApp: jest.fn().mockReturnValue(mockApp)
      };
      global.ScbdDrupalScbdFieldJs = {
        default: {}
      };

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Should have created a hidden field
      const hiddenField = document.querySelector('input[type="hidden"]');
      expect(hiddenField).toBeTruthy();
      expect(hiddenField.name).toContain('[0][value]');
    });

    test('should create mount element for Vue app', () => {
      // Mock Vue
      const mockMount = jest.fn();
      const mockApp = { mount: mockMount };
      global.Vue = {
        createApp: jest.fn().mockReturnValue(mockApp)
      };
      global.ScbdDrupalScbdFieldJs = {
        default: {}
      };

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Should have created the mount element
      const mountElement = document.querySelector('#bl-additional-fields');
      expect(mountElement).toBeTruthy();
    });
  });

  describe('Wrapper element detection', () => {
    test('should find #edit-field-tags-wrapper', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Found wrapper element for additional fields:', '#edit-field-tags-wrapper');
    });

    test('should fall back to form wrapper when specific wrapper not found', () => {
      document.body.innerHTML = `
        <form class="node-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Using form wrapper as fallback for additional fields');
    });

    test('should warn when no wrapper element is found', () => {
      document.body.innerHTML = `
        <div>
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
        </div>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.warn).toHaveBeenCalledWith('Bioland [additionalFields]: Could not find wrapper element for additional fields');
    });
  });

  describe('Content type change handling', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
            <option value="5">Project</option>
            <option value="1">Other</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should set up change event listener', () => {
      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(fieldElement.dataset.biolandAdditionalFieldsInit).toBe('true');
    });

    test('should not attach duplicate event listeners', () => {
      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      // Attach twice
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Event listeners already attached');
    });

    test('should handle content type change to type without additional fields', () => {
      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Change to content type 1 (no additional fields)
      fieldElement.value = '1';
      fieldElement.dispatchEvent(new Event('change'));
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: New content type should NOT have additional fields, removing if exists...');
    });

    test('should trigger handleContentTypeChange from keydown event with setTimeout', () => {
      // Mock Vue to allow listeners to be attached
      const mockMount = jest.fn();
      const mockApp = { mount: mockMount };
      global.Vue = {
        createApp: jest.fn().mockReturnValue(mockApp)
      };
      global.ScbdDrupalScbdFieldJs = { default: {} };
      
      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Clear logs from initialization
      console.log.mockClear();
      
      // Change value and trigger keydown
      fieldElement.value = '5';
      const keydownEvent = new KeyboardEvent('keydown', { key: 'ArrowDown' });
      fieldElement.dispatchEvent(keydownEvent);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Keydown event fired on content type field');
      
      // Advance past the setTimeout delay
      jest.advanceTimersByTime(100);
      
      // Should have processed the change after timeout
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Content type changed, updating additional fields:', '5');
    });

    test('should trigger handleContentTypeChange from mouseout event', () => {
      // Mock Vue to allow listeners to be attached
      const mockMount = jest.fn();
      const mockApp = { mount: mockMount };
      global.Vue = {
        createApp: jest.fn().mockReturnValue(mockApp)
      };
      global.ScbdDrupalScbdFieldJs = { default: {} };
      
      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Clear logs from initialization
      console.log.mockClear();
      
      // Change value and trigger mouseout
      fieldElement.value = '8';
      fieldElement.dispatchEvent(new Event('mouseout'));
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Mouseout event fired on content type field');
      // The content type changed handler should be triggered
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: handleContentTypeChange called');
    });
  });

  describe('Field name extraction', () => {
    test('should extract field name from wrapper ID', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Extracted field name suffix from wrapper:', 'tags');
    });

    test('should extract field name from thesaurus field', () => {
      document.body.innerHTML = `
        <form class="node-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <input class="edit-scbd_field-thesaurus-additional" name="field_custom[0][value2]" />
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Found thesaurus field suffix for additional fields:', 'custom');
    });

    test('should use default field name when extraction fails', () => {
      document.body.innerHTML = `
        <form class="node-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: No field name found, using default');
    });
  });

  describe('Content type mappings from settings', () => {
    test('should build mappings from additionalTags settings', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="10" selected>Custom</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = {
        bioland: {
          enableAdditionalFields: true,
          additionalTags: {
            eventStatusContentTypes: [10],
            projectStatusContentTypes: [11],
            organizationTypesContentTypes: [12],
            ecosystemTypesContentTypes: [13],
            documentTypesContentTypes: [14]
          }
        }
      };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Should have built custom mappings
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Content types with fields:', expect.arrayContaining([10, 11, 12, 13, 14]));
    });

    test('should use defaults when additionalTags is empty', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = {
        bioland: {
          enableAdditionalFields: true,
          additionalTags: {}
        }
      };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Should use default content types
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Content types with fields:', [3, 5, 8, 9, 12]);
    });
  });

  describe('Deferred content type check', () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should check again after delay if no initial value', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="">Select...</option>
            <option value="3">Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: No initial content type value found, checking again shortly...');
      
      // Set value during deferred check
      document.querySelector('#edit-field-type-placement').value = '3';
      
      // Advance timers
      jest.advanceTimersByTime(100);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: Deferred check found content type value:', '3');
    });
  });

  describe('Vue app error handling', () => {
    test('should handle Vue mount errors gracefully', () => {
      global.Vue = {
        createApp: jest.fn().mockImplementation(() => {
          throw new Error('Mount failed');
        })
      };
      global.ScbdDrupalScbdFieldJs = { default: {} };

      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      expect(console.error).toHaveBeenCalledWith('Bioland [additionalFields]: Error mounting additional fields Vue app:', expect.any(Error));
    });
  });

  describe('Additional fields removal', () => {
    test('should remove existing Vue app when changing to content type without fields', () => {
      const mockUnmount = jest.fn();
      const mockMount = jest.fn();
      const mockApp = { 
        mount: mockMount,
        unmount: mockUnmount
      };
      global.Vue = {
        createApp: jest.fn().mockReturnValue(mockApp)
      };
      global.ScbdDrupalScbdFieldJs = { default: {} };

      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
            <option value="1">Other</option>
          </select>
          <div id="edit-field-tags-wrapper"></div>
        </form>
      `;

      require('./bioland-additional-fields-1-0-42.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableAdditionalFields: true } };
      
      Drupal.behaviors.biolandAdditionalFields.attach(context, settings);
      
      // Create a mock Vue app on the element
      const mountElement = document.querySelector('#bl-additional-fields');
      if (mountElement) {
        mountElement.__vue_app__ = { unmount: mockUnmount };
      }
      
      // Change to content type without fields
      fieldElement.value = '1';
      fieldElement.dispatchEvent(new Event('change'));
      
      expect(console.log).toHaveBeenCalledWith('Bioland [additionalFields]: New content type should NOT have additional fields, removing if exists...');
    });
  });
});
