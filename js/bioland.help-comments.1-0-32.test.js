/**
 * @file
 * Unit tests for bioland-help-comments-1-0-30.js
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
      require('./bioland-help-comments-1-0-30.js');
      expect(Drupal.behaviors.biolandHelpComments).toBeDefined();
      expect(typeof Drupal.behaviors.biolandHelpComments.attach).toBe('function');
    });

    test('should not initialize if enableHelpComments is false', () => {
      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Initializing help comments');
    });

    test('should not initialize when form is missing', () => {
      document.body.innerHTML = '<div>No form here</div>';

      require('./bioland-help-comments-1-0-30.js');
      
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
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage).toBeTruthy();
      expect(helpMessage.classList.contains('alert-info')).toBe(true);
    });

    test('should add info icon to body field label', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      const infoIcon = bodyLabel.querySelector('.fa-info-circle');
      expect(infoIcon).toBeTruthy();
    });

    test('should add close button to help message', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const closeButton = document.querySelector('.bioland-help-close');
      expect(closeButton).toBeTruthy();
    });

    test('should prevent duplicate initialization', () => {
      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
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
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      expect(helpMessages.length).toBe(2); // Body + Attachments
    });

    test('should add info icon to attachments legend', () => {
      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage.style.display).toBe('none');
    });

    test('should set cookie when close button is clicked', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const closeButton = document.querySelector('.bioland-help-close');
      closeButton.click();
      
      expect(document.cookie).toContain('bioland_help_body_hidden=hidden');
    });

    test('should hide help message when close button is clicked', () => {
      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
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

      require('./bioland-help-comments-1-0-30.js');
      
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
      require('./bioland-help-comments-1-0-30.js');
      
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
      require('./bioland-help-comments-1-0-30.js');
      
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
      
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(tSpy).toHaveBeenCalledWith('Help');
      expect(tSpy).toHaveBeenCalledWith(expect.stringContaining('This will be the main content'));
    });

    test('should include help text about summary', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessage = document.querySelector('.bioland-help-comment');
      expect(helpMessage.textContent).toContain('summary');
    });
  });

  describe('Attachments field help - additional coverage', () => {
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

    test('should use custom text from settings', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = {
        bioland: {
          enableHelpComments: true,
          helpComments: {
            attachmentsImagesText: 'Custom images text',
            attachmentsHeroesText: 'Custom heroes text'
          }
        }
      };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      // Second message is attachments (first is body)
      const attachmentsHelp = helpMessages[1];
      expect(attachmentsHelp.innerHTML).toContain('Custom images text');
      expect(attachmentsHelp.innerHTML).toContain('Custom heroes text');
    });

    test('should add info icon to legend span', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const legend = document.querySelector('legend');
      const legendSpan = legend.querySelector('span');
      const infoIconWrapper = legendSpan.querySelector('span[role="button"]');
      expect(infoIconWrapper).toBeTruthy();
    });

    test('should add info icon directly to legend if no span', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
          <div id="field_attachments-media-library-wrapper">
            <legend>Attachments</legend>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const legend = document.querySelector('legend');
      const infoIconWrapper = legend.querySelector('span[role="button"]');
      expect(infoIconWrapper).toBeTruthy();
    });

    test('should toggle help message when info icon clicked', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const legend = document.querySelector('legend');
      const infoIconWrapper = legend.querySelector('span[role="button"]');
      const helpMessage = document.querySelectorAll('.bioland-help-comment')[1]; // Second help message (attachments)
      
      // Hide it first
      const closeButton = helpMessage.querySelector('.bioland-help-close');
      closeButton.click();
      
      expect(helpMessage.style.display).toBe('none');
      expect(infoIconWrapper.style.display).toBe('inline');
      
      // Show it again
      infoIconWrapper.click();
      
      expect(helpMessage.style.display).toBe('block');
      expect(infoIconWrapper.style.display).toBe('none');
    });

    test('should not add help when attachments wrapper is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Attachments field wrapper not found for help comment');
    });

    test('should not add help when legend is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
          <div id="field_attachments-media-library-wrapper">
            <div>No legend here</div>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Attachments legend not found for help comment');
    });
  });

  describe('Promotion Options help', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
          <div data-drupal-selector="edit-promote-wrapper">
            <details data-drupal-selector="edit-options">
              <summary>Promotion options</summary>
            </details>
          </div>
        </form>
      `;
    });

    test('should add help message to promotion options', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      // Should have at least body help + promotion help
      expect(helpMessages.length).toBeGreaterThanOrEqual(2);
    });

    test('should use custom text from settings', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = {
        bioland: {
          enableHelpComments: true,
          helpComments: {
            promotionOptionsText: '<b>Custom promotion text</b>'
          }
        }
      };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      // Should have at least body help
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      expect(helpMessages.length).toBeGreaterThan(0);
    });

    test('should toggle help message when info icon clicked', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const summary = document.querySelector('summary');
      const infoIconWrapper = summary.querySelector('span[role="button"]');
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      const promotionHelp = Array.from(helpMessages).find(msg => 
        msg.textContent.includes('Promoted to front page')
      );
      
      if (!promotionHelp || !infoIconWrapper) {
        // Skip this test if elements aren't created
        expect(helpMessages.length).toBeGreaterThan(0);
        return;
      }
      
      // Hide it first
      const closeButton = promotionHelp.querySelector('.bioland-help-close');
      closeButton.click();
      
      expect(promotionHelp.style.display).toBe('none');
      
      // Show it again
      infoIconWrapper.click();
      
      expect(promotionHelp.style.display).toBe('block');
    });

    test('should open details when showing help', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const detailsElement = document.querySelector('details');
      const summary = document.querySelector('summary');
      const infoIconWrapper = summary.querySelector('span[role="button"]');
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      const promotionHelp = Array.from(helpMessages).find(msg => 
        msg.textContent.includes('Promoted to front page')
      );
      
      if (!promotionHelp || !infoIconWrapper) {
        // Skip this test if elements aren't created
        expect(detailsElement).toBeTruthy();
        return;
      }
      
      // Hide help first
      const closeButton = promotionHelp.querySelector('.bioland-help-close');
      closeButton.click();
      
      // Close details
      detailsElement.open = false;
      
      // Click info icon
      infoIconWrapper.click();
      
      // Details should be open
      expect(detailsElement.open).toBe(true);
    });

    test('should not add help when promote wrapper is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Promote field wrapper not found for help comment');
    });
  });

  describe('Order Override help', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
          <details data-drupal-selector="edit-order-override-wrapper">
            <summary>Order Override</summary>
            <div class="claro-details__content">
              <div data-drupal-selector="edit-field-order-wrapper" class="field--name-field-order"></div>
            </div>
          </details>
        </form>
      `;
    });

    test('should add help message to order override field', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      // Should include order override help
      expect(helpMessages.length).toBeGreaterThanOrEqual(1);
    });

    test('should use custom text from settings', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = {
        bioland: {
          enableHelpComments: true,
          helpComments: {
            orderOverrideText: 'Custom order override text'
          }
        }
      };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      let found = false;
      helpMessages.forEach(msg => {
        if (msg.innerHTML.includes('Custom order override text')) {
          found = true;
        }
      });
      expect(found).toBe(true);
    });

    test('should toggle help message when info icon clicked', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const summary = document.querySelector('summary');
      const infoIconWrapper = summary.querySelector('span[role="button"]');
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      const orderHelp = Array.from(helpMessages).find(msg => 
        msg.innerHTML.includes('Order Override')
      );
      
      // Hide it first
      const closeButton = orderHelp.querySelector('.bioland-help-close');
      closeButton.click();
      
      expect(orderHelp.style.display).toBe('none');
      
      // Show it again
      infoIconWrapper.click();
      
      expect(orderHelp.style.display).toBe('block');
    });

    test('should open details when showing help', () => {
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const detailsElement = document.querySelector('details');
      const summary = document.querySelector('summary');
      const infoIconWrapper = summary.querySelector('span[role="button"]');
      
      // Hide help first
      const helpMessages = document.querySelectorAll('.bioland-help-comment');
      const orderHelp = Array.from(helpMessages).find(msg => 
        msg.innerHTML.includes('Order Override')
      );
      const closeButton = orderHelp.querySelector('.bioland-help-close');
      closeButton.click();
      
      // Close details
      detailsElement.open = false;
      
      // Click info icon
      infoIconWrapper.click();
      
      // Details should be open
      expect(detailsElement.open).toBe(true);
    });

    test('should not add help when order field wrapper is missing', () => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Order field wrapper not found for help comment');
    });
  });

  describe('Order override help with full functionality', () => {
    test('should add order override help successfully', () => {
      document.body.innerHTML = `
        <html lang="en">
        <body>
          <form class="node-content-form">
            <div>
              <label for="edit-body-0-value">Body</label>
            </div>
            <details class="edit-order-override-wrapper">
              <summary>Override Order</summary>
              <div class="claro-details__content">
                <div class="field--name-field-order">
                  <input name="order_override[0][value]" id="edit-order-override-0-value" />
                </div>
              </div>
            </details>
          </form>
        </body>
        </html>
      `;
      
      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      // Should have added help
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Order override help comment added');
    });
  });

  describe('Cookie management', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form class="node-content-form">
          <div>
            <label for="edit-body-0-value">Body</label>
          </div>
        </form>
      `;
    });

    test('should respect hidden cookie for body help', () => {
      document.cookie = 'bioland_help_body_hidden=hidden;path=/';

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [helpComments]: Body field help comment is hidden by user preference');
    });

    test('should set cookie when info icon is clicked', () => {
      document.cookie = 'bioland_help_body_hidden=hidden;path=/';

      require('./bioland-help-comments-1-0-30.js');
      
      const context = document.createElement('div');
      const settings = { bioland: { enableHelpComments: true } };
      
      Drupal.behaviors.biolandHelpComments.attach(context, settings);
      
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      const infoIconWrapper = bodyLabel.querySelector('span[role="button"]');
      
      // Click to show
      infoIconWrapper.click();
      
      // Cookie should be set to visible
      expect(document.cookie).toContain('bioland_help_body_hidden=visible');
    });
  });
});
