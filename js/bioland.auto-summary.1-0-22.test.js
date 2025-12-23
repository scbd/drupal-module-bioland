/**
 * @file
 * Unit tests for bioland-auto-summary-1-0-22.js
 */

describe('Bioland Auto Summary', () => {
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

    // Mock CKEDITOR (not present by default)
    global.CKEDITOR = undefined;

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
    console.warn = originalConsoleWarn;
    console.error = originalConsoleError;
  });

  describe('Drupal behavior registration', () => {
    test('should register biolandAutoSummary behavior', () => {
      require('./bioland-auto-summary-1-0-22.js');
      expect(Drupal.behaviors.biolandAutoSummary).toBeDefined();
      expect(typeof Drupal.behaviors.biolandAutoSummary.attach).toBe('function');
    });

    test('should not initialize if enableAutoSummary is false', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: false } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Auto summary is disabled in settings');
    });
  });

  describe('Summary field detection', () => {
    test('should find summary field by data-drupal-selector', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea data-drupal-selector="edit-body-0-summary"></textarea>
        </form>
      `;

      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found summary field with selector:', 'textarea[data-drupal-selector="edit-body-0-summary"]');
    });

    test('should find summary field by id', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
        </form>
      `;

      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found summary field with selector:', '#edit-body-0-summary');
    });

    test('should find summary field by name attribute', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea name="body[0][summary]"></textarea>
        </form>
      `;

      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found summary field with selector:', 'textarea[name="body[0][summary]"]');
    });

    test('should handle missing summary field gracefully', () => {
      document.body.innerHTML = '<form class="node-content-form"></form>';

      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Summary field not found, cannot enable auto-summary');
    });
  });

  describe('Initialization tracking', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <textarea id="edit-body-0-value"></textarea>
        </form>
      `;
    });

    test('should prevent duplicate initialization', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      // Attach twice
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Auto summary already initialized');
    });

    test('should set initialization marker on summary field', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(summaryField.dataset.biolandAutoSummaryInit).toBe('true');
    });
  });

  describe('User edit detection', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <textarea id="edit-body-0-value"></textarea>
        </form>
      `;
    });

    test('should track user edits to summary field via input event', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Simulate user input
      summaryField.dispatchEvent(new Event('input'));
      
      expect(summaryField.dataset.biolandUserEdited).toBe('true');
    });

    test('should track user edits to summary field via keyup event', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Simulate user keyup
      summaryField.dispatchEvent(new Event('keyup'));
      
      expect(summaryField.dataset.biolandUserEdited).toBe('true');
    });
  });

  describe('Plain textarea integration', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      
      // Set up proper DOM structure
      document.documentElement.setAttribute('lang', 'en');
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <textarea id="edit-body-0-value">Hello world. This is a test.</textarea>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should set up plain textarea when no CKEditor detected', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // When no CKEditor is detected, it falls through to plain textarea setup
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: No CKEditor detected, using plain textarea');
    });

    test('should update summary from body field on initial load', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Should have updated the summary - the plain textarea handler triggers on initial load
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Plain textarea auto-summary fully initialized');
    });

    test('should update summary when body field changes', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Clear the summary field to test update
      summaryField.value = '';
      
      // Change body content
      bodyField.value = 'New content here. This is different.';
      bodyField.dispatchEvent(new Event('input'));
      
      // Run debounce timer
      jest.advanceTimersByTime(300);
      
      // The auto summary should have been triggered
      expect(summaryField.value).toContain('New content here');
    });

    test('should not update summary when user has manually edited it', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // User edits summary
      summaryField.value = 'My custom summary';
      summaryField.dispatchEvent(new Event('input'));
      
      // Change body content
      bodyField.value = 'Completely different content.';
      bodyField.dispatchEvent(new Event('input'));
      
      // Run debounce timer
      jest.advanceTimersByTime(300);
      
      // Summary should not have changed
      expect(summaryField.value).toBe('My custom summary');
    });
  });

  describe('HTML stripping', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      document.documentElement.setAttribute('lang', 'en');
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <textarea id="edit-body-0-value"></textarea>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should strip HTML tags from content', () => {
      document.querySelector('#edit-body-0-value').value = '<p>Hello <strong>world</strong>. This is <em>formatted</em>.</p>';
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update by simulating input
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value).not.toContain('<p>');
      expect(summaryField.value).not.toContain('<strong>');
      expect(summaryField.value).not.toContain('<em>');
      expect(summaryField.value).toContain('Hello world');
    });

    test('should normalize whitespace', () => {
      document.querySelector('#edit-body-0-value').value = 'Hello    world.\n\n\nThis   is a   test.';
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      // Should not have multiple consecutive spaces
      expect(summaryField.value).not.toMatch(/\s{2,}/);
    });
  });

  describe('Smart truncation', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      document.documentElement.setAttribute('lang', 'en');
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <textarea id="edit-body-0-value"></textarea>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should truncate long text', () => {
      const longText = 'This is a sentence. '.repeat(50);
      document.querySelector('#edit-body-0-value').value = longText;
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value.length).toBeLessThanOrEqual(255);
    });

    test('should not truncate short text', () => {
      const shortText = 'Short sentence.';
      document.querySelector('#edit-body-0-value').value = shortText;
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value).toBe('Short sentence.');
    });

    test('should try to end at sentence boundary', () => {
      const text = 'First sentence. Second sentence. Third sentence is much longer and continues for a while to make this text long enough to need truncation. Fourth sentence. Fifth sentence. Sixth sentence. Seventh sentence.';
      document.querySelector('#edit-body-0-value').value = text;
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      // Should end with a period (sentence boundary) or ellipsis
      expect(summaryField.value).toMatch(/\.$|\.{3}$/);
    });
  });  describe('CKEditor 4 integration', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      
      document.body.innerHTML = `
        <html lang="en">
        <body>
          <form class="node-content-form">
            <textarea id="edit-body-0-summary"></textarea>
            <textarea id="edit-body-0-value"></textarea>
          </form>
        </body>
        </html>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should detect CKEditor 4 when available', () => {
      global.CKEDITOR = {
        instances: {}
      };
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor 4 detected, attempting to connect...');
    });

    test('should connect to CKEditor 4 instance when available', () => {
      const mockOn = jest.fn();
      const mockGetData = jest.fn().mockReturnValue('Initial content.');
      
      global.CKEDITOR = {
        instances: {
          'edit-body-0-value': {
            on: mockOn,
            getData: mockGetData
          }
        }
      };
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for the interval to find the instance
      jest.advanceTimersByTime(100);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor 4 instance found:', 'edit-body-0-value');
    });
  });

  describe('CKEditor 5 integration', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      
      document.body.innerHTML = `
        <html lang="en">
        <body>
          <form class="node-content-form">
            <textarea id="edit-body-0-summary"></textarea>
            <textarea id="edit-body-0-value" data-drupal-selector="edit-body-0-value"></textarea>
          </form>
        </body>
        </html>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should detect CKEditor 5 when available', () => {
      global.Drupal.CKEditor5Instances = new Map();
      
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor 5 detected, attempting to connect...');
    });
  });

  describe('Contenteditable fallback', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      document.documentElement.setAttribute('lang', 'en');
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
          <div class="ck-editor__editable" contenteditable="true">Hello contenteditable world.</div>
        </form>
      `;
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    test('should detect contenteditable div', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // The code checks for contenteditable after checking for CKEditor
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor detected via contenteditable div, using direct monitor...');
    });

    test('should set up contenteditable monitor', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(editableDiv.dataset.biolandAutoSummaryInit).toBe('true');
    });

    test('should update summary from contenteditable content', () => {
      require('./bioland-auto-summary-1-0-22.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for initial update to process
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value).toContain('Hello contenteditable world');
    });
  });
});
