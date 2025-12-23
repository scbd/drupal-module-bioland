/**
 * @file
 * Unit tests for bioland-help-comments-1-0-23.js
 */

describe('Bioland Help Comments', () => {
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

    // Clear cookies
    document.cookie.split(';').forEach((c) => {
      document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
    });

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
    console.warn = originalConsoleWarn;
  });

  describe('Drupal behavior registration', () => {
    test('should register biolandHelpComments behavior', () => {
      require('./bioland-help-comments-1-0-23.js');
      expect(Drupal.behaviors.biolandHelpComments).toBeDefined();
      expect(typeof Drupal.behaviors.biolandHelpComments.attach).toBe('function');
    });

    test('should not initialize if enableHelpComments is false', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: false } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      // Should not log initialization
      expect(console.log).not.toHaveBeenCalledWith('Bioland [helpComments]: Initializing help comments');
    });
  });

  describe('Form detection', () => {
    test('should detect node-content-form', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <label for="edit-body-0-value">Body</label>
        </form>
      `;

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Initializing help comments');
    });

    test('should detect node-content-edit-form', () => {
      document.body.innerHTML = `
        <form class="node-content-edit-form">
          <label for="edit-body-0-value">Body</label>
        </form>
      `;

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Initializing help comments');
    });

    test('should not initialize when form is missing', () => {
      document.body.innerHTML = '<div>No form here</div>';

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Content form not found for help comments');
    });
  });

  describe('Body field help', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should add help message after body field label', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage).toBeTruthy();
      expect(helpMessage.classList.contains('alert-info')).toBe(true);
    });

    test('should add info icon to body field label', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      const infoIcon = bodyLabel.querySelector('.fa-info-circle');
      expect(infoIcon).toBeTruthy();
    });

    test('should add close button to help message', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const closeButton = document.querySelector('.bioland-help-close');
      expect(closeButton).toBeTruthy();
    });

    test('should prevent duplicate initialization', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      // Should only have one help message
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      expect(helpMessages.length).toBe(1);
    });

    test('should not add help when body label is missing', () => {
      document.body.innerHTML = '<form class="node-content-form"></form>';

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Body field label not found for help comment');
    });
  });

  describe('Attachments field help', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
          <div id="field_attachments-media-library-wrapper">
            <legend><span>Attachments</span></legend>
          </div>
        </form>
      `;
    });

    test('should add help message after attachments legend', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      expect(helpMessages.length).toBe(2); // Body + Attachments
    });

    test('should add info icon to attachments legend', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const attachmentsWrapper = document.querySelector('#field_attachments-media-library-wrapper');
      const legendSpan = attachmentsWrapper.querySelector('legend span');
      const infoIcon = legendSpan.querySelector('.fa-info-circle');
      expect(infoIcon).toBeTruthy();
    });

    test('should not add help when attachments wrapper is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Attachments field wrapper not found for help comment');
    });
  });

  describe('Cookie persistence', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should hide help message when cookie indicates hidden', () => {
      // Set cookie before initializing
      document.cookie = 'bioland_help_body_hidden=hidden;path=/';

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage.style.display).toBe('none');
    });

    test('should set cookie when close button is clicked', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const closeButton = document.querySelector('.bioland-help-close');
      closeButton.click();
      
      expect(document.cookie).toContain('bioland_help_body_hidden=hidden');
    });

    test('should hide help message when close button is clicked', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      const closeButton = document.querySelector('.bioland-help-close');
      
      closeButton.click();
      
      expect(helpMessage.style.display).toBe('none');
    });
  });

  describe('Info icon toggle', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should show info icon when help message is hidden via cookie', () => {
      document.cookie = 'bioland_help_body_hidden=hidden;path=/';

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      // Info icon is wrapped in a span for click handling (FontAwesome SVG replacement)
      const infoIconWrapper = bodyLabel.querySelector('span[role="button"]');
      expect(infoIconWrapper.style.display).toBe('inline');
    });

    test('should toggle help message visibility when info icon is clicked', () => {
      document.cookie = 'bioland_help_body_hidden=hidden;path=/';

      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      // Info icon is wrapped in a span for click handling (FontAwesome SVG replacement)
      const infoIconWrapper = bodyLabel.querySelector('span[role="button"]');
      const helpMessage = document.querySelector('.bioland-help-comment');
      
      // Initially hidden
      expect(helpMessage.style.display).toBe('none');
      
      // Click to show
      infoIconWrapper.click();
      expect(helpMessage.style.display).toBe('block');
      
      // Click to hide again
      infoIconWrapper.click();
      expect(helpMessage.style.display).toBe('none');
    });
  });

  describe('Close button interactions', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should show info icon when close button is clicked', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      // Info icon is wrapped in a span for click handling (FontAwesome SVG replacement)
      const infoIconWrapper = bodyLabel.querySelector('span[role="button"]');
      const closeButton = document.querySelector('.bioland-help-close');
      
      // Initially info icon wrapper should be hidden (help message visible)
      expect(infoIconWrapper.style.display).toBe('none');
      
      closeButton.click();
      
      // After closing, info icon wrapper should be visible
      expect(infoIconWrapper.style.display).toBe('inline');
    });

    test('should have hover effect on close button', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const closeButton = document.querySelector('.bioland-help-close');
      
      // Simulate mouseenter
      closeButton.dispatchEvent(new Event('mouseenter'));
      expect(closeButton.style.opacity).toBe('1');
      
      // Simulate mouseleave
      closeButton.dispatchEvent(new Event('mouseleave'));
      expect(closeButton.style.opacity).toBe('0.6');
    });
  });

  describe('Translated strings', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should use Drupal.t for translatable strings', () => {
      const tSpy = jest.spyOn(Drupal, 't');
      
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(tSpy).toHaveBeenCalledWith('Help');
      expect(tSpy).toHaveBeenCalledWith(expect.stringContaining('This will be the main content'));
    });

    test('should include help text about summary', () => {
      require('./bioland-help-comments-1-0-23.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage.textContent).toContain('summary');
    });
  });
});
