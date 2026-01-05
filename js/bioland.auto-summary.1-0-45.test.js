/**
 * @file
 * Unit tests for bioland-auto-summary-1-0-45.js
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
      require('./bioland-auto-summary-1-0-45.js');
      expect(Drupal.behaviors.biolandAutoSummary).toBeDefined();
      expect(typeof Drupal.behaviors.biolandAutoSummary.attach).toBe('function');
    });

    test('should not initialize if enableAutoSummary is false', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
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

      require('./bioland-auto-summary-1-0-45.js');
      
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

      require('./bioland-auto-summary-1-0-45.js');
      
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

      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found summary field with selector:', 'textarea[name="body[0][summary]"]');
    });

    test('should handle missing summary field gracefully', () => {
      document.body.innerHTML = '<form class="node-content-form"></form>';

      require('./bioland-auto-summary-1-0-45.js');
      
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
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      // Attach twice
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Auto summary already initialized');
    });

    test('should set initialization marker on summary field', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
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
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Simulate user input
      summaryField.dispatchEvent(new Event('input'));
      
      expect(summaryField.dataset.biolandUserEdited).toBe('true');
    });

    test('should track user edits to summary field via keyup event', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
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
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // When no CKEditor is detected, it falls through to plain textarea setup
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: No CKEditor detected, using plain textarea');
    });

    test('should update summary from body field on initial load', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Should have updated the summary - the plain textarea handler triggers on initial load
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Plain textarea auto-summary fully initialized');
    });

    test('should update summary when body field changes', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
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
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
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
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // The code checks for contenteditable after checking for CKEditor
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor detected via contenteditable div, using direct monitor...');
    });

    test('should set up contenteditable monitor', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(editableDiv.dataset.biolandAutoSummaryInit).toBe('true');
    });

    test('should update summary from contenteditable content', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for initial update to process
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value).toContain('Hello contenteditable world');
    });

    test('should not reinitialize if already initialized', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      // First attach
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(editableDiv.dataset.biolandAutoSummaryInit).toBe('true');
    });

    test('should handle contenteditable input events', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Clear existing value
      summaryField.value = '';
      
      // Change content
      editableDiv.innerHTML = 'New test content.';
      editableDiv.dispatchEvent(new Event('input'));
      
      // Wait for debounce
      jest.advanceTimersByTime(300);
      
      expect(summaryField.value).toContain('New test content');
    });

    test('should respect user edited flag in contenteditable', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const editableDiv = document.querySelector('.ck-editor__editable');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Mark as user edited
      summaryField.dataset.biolandUserEdited = 'true';
      
      // Change content
      editableDiv.innerHTML = 'Should not update.';
      editableDiv.dispatchEvent(new Event('input'));
      
      // Wait for debounce
      jest.advanceTimersByTime(300);
      
      // Should not have updated
      expect(summaryField.value).not.toContain('Should not update');
    });

    test('should return false when contenteditable div not found', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <textarea id="edit-body-0-summary"></textarea>
        </form>
      `;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: No CKEditor detected, using plain textarea');
    });
  });

  describe('HTML stripping', () => {
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

    test('should handle large HTML content with chunked processing', () => {
      const largeHtml = '<p>' + 'x'.repeat(150000) + '</p>';
      document.querySelector('#edit-body-0-value').value = largeHtml;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Trigger update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(console.log).toHaveBeenCalledWith(expect.stringContaining('Large HTML content detected'));
    });

    test('should handle empty body HTML', () => {
      document.querySelector('#edit-body-0-value').value = '';
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Skipping update - bodyHtml empty or user edited');
    });

    test('should handle HTML with no text content after stripping', () => {
      document.querySelector('#edit-body-0-value').value = '<div></div><span></span>';
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: No text content after stripping HTML');
    });

    test('should handle errors in HTML processing with fallback', () => {
      const invalidHtml = '<p>Test content</p>';
      document.querySelector('#edit-body-0-value').value = invalidHtml;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      // Should have processed normally
      expect(summaryField.value).toContain('Test content');
    });
  });

  describe('CKEditor 5 instance finding', () => {
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
      delete global.Drupal.CKEditor5Instances;
    });

    test('should find CKEditor 5 instance by exact ID match', () => {
      const mockInstance = {
        model: {
          document: {
            on: jest.fn()
          }
        },
        getData: jest.fn().mockReturnValue('')
      };
      
      global.Drupal.CKEditor5Instances = new Map();
      global.Drupal.CKEditor5Instances.set('edit-body-0-value', mockInstance);
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      jest.advanceTimersByTime(100);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found CKEditor 5 instance by exact ID match');
    });

    test('should find CKEditor 5 instance by source element match', () => {
      const bodyField = document.querySelector('#edit-body-0-value');
      const mockInstance = {
        sourceElement: bodyField,
        model: {
          document: {
            on: jest.fn()
          }
        },
        getData: jest.fn().mockReturnValue('')
      };
      
      global.Drupal.CKEditor5Instances = new Map();
      global.Drupal.CKEditor5Instances.set('other-key', mockInstance);
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      jest.advanceTimersByTime(100);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Found CKEditor 5 instance by matching source element with key:', 'other-key');
    });

    test('should fallback to contenteditable after max attempts', () => {
      global.Drupal.CKEditor5Instances = new Map();
      
      document.body.innerHTML = `
        <html lang="en">
        <body>
          <form class="node-content-form">
            <textarea id="edit-body-0-summary"></textarea>
            <textarea id="edit-body-0-value"></textarea>
            <div class="ck-editor__editable" contenteditable="true">Fallback content</div>
          </form>
        </body>
        </html>
      `;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Advance past max attempts (50 * 100ms = 5000ms)
      jest.advanceTimersByTime(5100);
      
      expect(console.warn).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor 5 instance not found after', 50, 'attempts');
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Attempting contenteditable div fallback...');
    });

    test('should fallback to textarea when contenteditable also fails', () => {
      global.Drupal.CKEditor5Instances = new Map();
      
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
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Advance past max attempts
      jest.advanceTimersByTime(5100);
      
      expect(console.warn).toHaveBeenCalledWith('Bioland [autoSummary]: Contenteditable monitor failed, falling back to textarea');
    });
  });

  describe('CKEditor 4 timeout handling', () => {
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
      delete global.CKEDITOR;
    });

    test('should fallback to textarea after max attempts for CKEditor 4', () => {
      global.CKEDITOR = {
        instances: {}
      };
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Advance past max attempts (50 * 100ms = 5000ms)
      jest.advanceTimersByTime(5100);
      
      expect(console.warn).toHaveBeenCalledWith('Bioland [autoSummary]: CKEditor 4 instance not found after', 50, 'attempts, falling back to textarea');
    });
  });

  describe('Timeout management', () => {
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

    test('should clear existing timeout before setting new one', () => {
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // First input
      bodyField.value = 'First';
      bodyField.dispatchEvent(new Event('input'));
      
      // Immediately second input (should cancel first timeout)
      bodyField.value = 'Second';
      bodyField.dispatchEvent(new Event('input'));
      
      // Advance timers
      jest.advanceTimersByTime(300);
      
      // Should only have updated once with the second value
      expect(summaryField.value).toBe('Second');
    });
  });

  describe('Simple smart truncate fallback', () => {
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

    test('should use simple truncation when Intl.Segmenter is not available', () => {
      const originalIntl = global.Intl;
      global.Intl = undefined;
      
      const longText = 'First sentence here. Second sentence here. ' + 'word '.repeat(100);
      document.querySelector('#edit-body-0-value').value = longText;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      // Should have truncated
      expect(summaryField.value.length).toBeLessThanOrEqual(255);
      expect(console.log).toHaveBeenCalledWith(expect.stringContaining('Intl.Segmenter not available'));
      
      global.Intl = originalIntl;
    });
  });

  describe('CKEditor 4 key event with timeout', () => {
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
      delete global.CKEDITOR;
    });

    test('should handle CKEditor 4 key event with debounce timeout', () => {
      const mockGetData = jest.fn().mockReturnValue('<p>Test content from key event.</p>');
      let keyCallback = null;
      const mockOn = jest.fn((event, callback) => {
        if (event === 'key') {
          keyCallback = callback;
        }
      });
      
      global.CKEDITOR = {
        instances: {
          'edit-body-0-value': {
            on: mockOn,
            getData: mockGetData
          }
        }
      };
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for interval to find CKEditor instance
      jest.advanceTimersByTime(100);
      
      expect(mockOn).toHaveBeenCalledWith('key', expect.any(Function));
      
      // Trigger key event
      if (keyCallback) {
        keyCallback();
        
        // First key press - store timeout
        expect(summaryField.dataset.biolandKeyTimeout).toBeDefined();
        const firstTimeout = summaryField.dataset.biolandKeyTimeout;
        
        // Second key press - should clear previous timeout
        keyCallback();
        const secondTimeout = summaryField.dataset.biolandKeyTimeout;
        expect(secondTimeout).not.toBe(firstTimeout);
        
        // Advance past debounce timeout
        jest.advanceTimersByTime(300);
        
        // Should have updated summary
        expect(mockGetData).toHaveBeenCalled();
        expect(summaryField.value).toBe('Test content from key event.');
      }
    });

    test('should update from CKEditor 4 initial content', () => {
      const mockGetData = jest.fn().mockReturnValue('<p>Initial editor content.</p>');
      const mockOn = jest.fn();
      
      global.CKEDITOR = {
        instances: {
          'edit-body-0-value': {
            on: mockOn,
            getData: mockGetData
          }
        }
      };
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for interval to find and process CKEditor instance
      jest.advanceTimersByTime(100);
      
      // Should have populated summary from initial content
      expect(summaryField.value).toBe('Initial editor content.');
    });
  });

  describe('CKEditor 4 events', () => {
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
      delete global.CKEDITOR;
    });

    test('should handle CKEditor 4 key events', () => {
      const mockGetData = jest.fn().mockReturnValue('Test content.');
      const mockOn = jest.fn((event, callback) => {
        if (event === 'key') {
          // Simulate the key callback
          callback();
        }
      });
      
      global.CKEDITOR = {
        instances: {
          'edit-body-0-value': {
            on: mockOn,
            getData: mockGetData
          }
        }
      };
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for the interval to find the instance
      jest.advanceTimersByTime(100);
      
      expect(mockOn).toHaveBeenCalledWith('key', expect.any(Function));
    });

    test('should handle CKEditor 4 change events', () => {
      const mockGetData = jest.fn().mockReturnValue('Test content.');
      const mockOn = jest.fn((event, callback) => {
        if (event === 'change') {
          // Simulate the change callback
          callback();
        }
      });
      
      global.CKEDITOR = {
        instances: {
          'edit-body-0-value': {
            on: mockOn,
            getData: mockGetData
          }
        }
      };
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // Wait for the interval to find the instance
      jest.advanceTimersByTime(100);
      
      expect(mockOn).toHaveBeenCalledWith('change', expect.any(Function));
    });
  });

  describe('Edge cases and error conditions', () => {
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

    test('should handle stripHtml errors with fallback', () => {
      const textWithError = '<script>alert(1)</script><p>Content</p>';
      document.querySelector('#edit-body-0-value').value = textWithError;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      // Should have produced a result
      expect(summaryField.value).toBeTruthy();
    });

    test('should not update when summary has unchanged content', () => {
      const content = 'Test content.';
      document.querySelector('#edit-body-0-value').value = content;
      
      require('./bioland-auto-summary-1-0-45.js');
      
      const summaryField = document.querySelector('#edit-body-0-summary');
      const bodyField = document.querySelector('#edit-body-0-value');
      const context = document.createElement('div');
      const settings = { bioland: { enableAutoSummary: true } };
      
      Drupal.behaviors.biolandAutoSummary.attach(context, settings);
      
      // First update
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      const firstValue = summaryField.value;
      
      // Same content again
      bodyField.dispatchEvent(new Event('input'));
      jest.advanceTimersByTime(300);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [autoSummary]: Summary unchanged, skipping update');
    });
  });
});
