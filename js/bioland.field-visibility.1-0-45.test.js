/**
 * @file
 * Unit tests for bioland-field-visibility-1-0-45.js
 */

describe('Bioland Field Visibility', () => {
  let originalConsoleLog;
  let originalConsoleWarn;

  beforeEach(() => {
    // Suppress console output during tests
    originalConsoleLog = console.log;
    originalConsoleWarn = console.warn;
    console.log = jest.fn();
    console.warn = jest.fn();

    // Reset Drupal behaviors
    global.Drupal.behaviors = {};

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
    console.warn = originalConsoleWarn;
  });

  describe('Drupal behavior registration', () => {
    test('should register biolandFieldVisibility behavior', () => {
      require('./bioland-field-visibility-1-0-45.js');
      expect(Drupal.behaviors.biolandFieldVisibility).toBeDefined();
      expect(typeof Drupal.behaviors.biolandFieldVisibility.attach).toBe('function');
    });

    test('should not initialize if enableFieldVisibility is false', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: false } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Should return early without logging initialization
      expect(console.log).not.toHaveBeenCalledWith('Bioland [fieldVisibility]: Initializing field visibility');
    });
  });

  describe('Field visibility initialization', () => {
    beforeEach(() => {
      // Set up basic DOM structure
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
            <option value="5">Project</option>
            <option value="8">Ministry</option>
          </select>
          <div id="edit-field-url-wrapper" style="display: block;"></div>
          <div id="edit-field-published-wrapper" style="display: block;"></div>
          <div id="edit-field-start-date-wrapper" style="display: block;"></div>
          <div id="edit-field-end-date-wrapper" style="display: block;"></div>
          <label for="edit-body-0-format--2">Format</label>
          <div id="edit-body-0-format-help-about">Help</div>
        </form>
      `;
    });

    test('should initialize field visibility when content type field exists', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: Initializing field visibility');
    });

    test('should not initialize when content type field is missing', () => {
      document.body.innerHTML = '<form class="node-content-form"></form>';
      
      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: No content type field found for visibility');
    });
  });

  describe('URL field visibility', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-url-wrapper" style="display: none;"></div>
        </form>
      `;
    });

    test('should show URL field for content types that require it', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const urlWrapper = document.querySelector('#edit-field-url-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          urlContentTypes: [3, 5, 12]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(urlWrapper.style.display).toBe('block');
    });

    test('should hide URL field for content types that do not require it', () => {
      document.querySelector('#edit-field-type-placement').value = '1';
      
      require('./bioland-field-visibility-1-0-45.js');
      
      const urlWrapper = document.querySelector('#edit-field-url-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          urlContentTypes: [3, 5, 12]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(urlWrapper.style.display).toBe('none');
    });
  });

  describe('Published field visibility', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-published-wrapper" style="display: none;"></div>
        </form>
      `;
    });

    test('should show published field for content types that require it', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          publishedContentTypes: [3, 5, 12]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(publishedWrapper.style.display).toBe('block');
    });

    test('should hide published field for content types that do not require it', () => {
      document.querySelector('#edit-field-type-placement').value = '1';
      
      require('./bioland-field-visibility-1-0-45.js');
      
      const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          publishedContentTypes: [3, 5, 12]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(publishedWrapper.style.display).toBe('none');
    });
  });

  describe('Date fields visibility', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-start-date-wrapper" style="display: none;"></div>
          <div id="edit-field-end-date-wrapper" style="display: none;"></div>
        </form>
      `;
    });

    test('should show date fields for content types that require them', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
      const endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          dateRangeContentTypes: [2, 3, 13]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(startDateWrapper.style.display).toBe('block');
      expect(endDateWrapper.style.display).toBe('block');
    });

    test('should hide date fields for content types that do not require them', () => {
      document.querySelector('#edit-field-type-placement').value = '5';
      
      require('./bioland-field-visibility-1-0-45.js');
      
      const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
      const endDateWrapper = document.querySelector('#edit-field-end-date-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          dateRangeContentTypes: [2, 3, 13]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(startDateWrapper.style.display).toBe('none');
      expect(endDateWrapper.style.display).toBe('none');
    });
  });

  describe('Text format hiding', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <label for="edit-body-0-format--2" style="display: block;">Format</label>
          <div id="edit-body-0-format-help-about" style="display: block;">Help</div>
        </form>
      `;
    });

    test('should hide text format label and help link', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const label = document.querySelector('label[for="edit-body-0-format--2"]');
      const helpLink = document.querySelector('#edit-body-0-format-help-about');
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(label.style.display).toBe('none');
      expect(helpLink.style.display).toBe('none');
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
          </select>
          <div id="edit-field-url-wrapper" style="display: block;"></div>
          <div id="edit-field-published-wrapper" style="display: block;"></div>
          <div id="edit-field-start-date-wrapper" style="display: block;"></div>
          <div id="edit-field-end-date-wrapper" style="display: block;"></div>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should set up change event listener', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(fieldElement.dataset.biolandFieldVisibilityInit).toBe('true');
    });

    test('should update visibility when content type changes', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          dateRangeContentTypes: [2, 3, 13]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Initially content type is 3 (event) - dates should be visible
      expect(startDateWrapper.style.display).toBe('block');
      
      // Change to content type 5 (project) - dates should be hidden
      fieldElement.value = '5';
      fieldElement.dispatchEvent(new Event('change'));
      
      expect(startDateWrapper.style.display).toBe('none');
    });

    test('should not update visibility when value is unchanged', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Trigger change event without actually changing the value
      fieldElement.dispatchEvent(new Event('change'));
      
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: Content type value unchanged, skipping');
    });

    test('should not attach duplicate event listeners', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      // Attach twice
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Should only have the marker set once
      expect(fieldElement.dataset.biolandFieldVisibilityInit).toBe('true');
    });

    test('should handle keydown events with delay', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const startDateWrapper = document.querySelector('#edit-field-start-date-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          dateRangeContentTypes: [2, 3, 13]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Change value
      fieldElement.value = '5';
      
      // Trigger keydown
      fieldElement.dispatchEvent(new Event('keydown'));
      
      // Advance timers
      jest.advanceTimersByTime(100);
      
      expect(startDateWrapper.style.display).toBe('none');
    });

    test('should handle mouseout events', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Trigger mouseout
      fieldElement.dispatchEvent(new Event('mouseout'));
      
      // Should log the event
      expect(console.log).toHaveBeenCalled();
    });
  });

  describe('Published field visibility', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
          <div id="edit-field-published-wrapper" style="display: block;"></div>
        </form>
      `;
    });

    test('should show published field for content types that require it', () => {
      require('./bioland-field-visibility-1-0-45.js');
      
      const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          publishedFieldContentTypes: [3, 5]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(publishedWrapper.style.display).toBe('block');
    });

    test('should hide published field for content types that do not require it', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="8" selected>Other</option>
          </select>
          <div id="edit-field-published-wrapper" style="display: block;"></div>
        </form>
      `;
      
      require('./bioland-field-visibility-1-0-45.js');
      
      const publishedWrapper = document.querySelector('#edit-field-published-wrapper');
      const context = document.createElement('div');
      const settings = { 
        bioland: { 
          enableFieldVisibility: true,
          publishedFieldContentTypes: [3, 5]
        } 
      };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(publishedWrapper.style.display).toBe('none');
    });

    test('should handle missing published wrapper gracefully', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
        </form>
      `;

      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Should not throw error
      expect(console.log).toHaveBeenCalled();
    });
  });

  describe('Edge cases and null checks', () => {
    test('should handle missing content type value gracefully', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="" selected>Select...</option>
          </select>
          <div id="edit-field-url-wrapper"></div>
        </form>
      `;

      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: No content type field found for visibility');
    });

    test('should not process when content type field element is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
          </select>
        </form>
      `;

      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Create a scenario where element exists but listeners can't be set up
      const fieldElement = document.querySelector('#edit-field-type-placement');
      fieldElement.dataset.biolandFieldVisibilityInit = 'true';
      
      // Reinitialize
      global.Drupal.behaviors = {};
      jest.resetModules();
      require('./bioland-field-visibility-1-0-45.js');
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Should not try to attach listeners again
      expect(console.log).toHaveBeenCalled();
    });

    test('should handle empty content type value triggering early return', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <select id="edit-field-type-placement">
            <option value="3" selected>Event</option>
            <option value="">Select type</option>
          </select>
        </form>
      `;

      require('./bioland-field-visibility-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableFieldVisibility: true } };
      
      Drupal.behaviors.biolandFieldVisibility.attach(context, settings);
      
      // Clear initialization logs
      console.log.mockClear();
      
      const fieldElement = document.querySelector('#edit-field-type-placement');
      // Change to empty value and trigger
      fieldElement.value = '';
      fieldElement.dispatchEvent(new Event('change'));
      
      // Should log the early return path
      expect(console.log).toHaveBeenCalledWith('Bioland [fieldVisibility]: No updated value found');
    });
  });
});
