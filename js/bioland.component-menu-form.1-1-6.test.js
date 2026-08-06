/**
 * @file
 * Unit tests for bioland-component-menu-form-1-1-6.js
 */

describe('Bioland Component Menu Form', () => {
  /**
   * Builds the Component-mode form DOM the behavior expects.
   */
  function buildForm() {
    document.body.innerHTML = `
      <form class="bioland-component-menu-form">
        <select name="bioland_component">
          <option value="" selected></option>
          <option value="bl2-component-forums">Forums</option>
          <option value="bl2-component-content-type">Content Type Listing</option>
        </select>
        <select name="bioland_component_content_type">
          <option value="" selected>- Select -</option>
          <option value="news">News</option>
          <option value="event">Events</option>
        </select>
        <input name="title[0][value]" value="">
        <input name="link[0][uri]" value="">
      </form>
    `;
    return document.querySelector('form');
  }

  function el(name) {
    return document.querySelector('[name="' + name + '"]');
  }

  function pick(name, value) {
    const select = el(name);
    select.value = value;
    select.dispatchEvent(new Event('change'));
  }

  beforeEach(() => {
    global.Drupal.behaviors = {};
    // core/once stand-in: mark-and-filter, like the real library.
    global.once = (id, selector, context) => {
      const attribute = 'data-once-' + id;
      return Array.from((context || document).querySelectorAll(selector))
        .filter((element) => !element.hasAttribute(attribute))
        .map((element) => {
          element.setAttribute(attribute, '');
          return element;
        });
    };
    jest.resetModules();
    require('./bioland-component-menu-form-1-1-6.js');
  });

  test('registers the behavior', () => {
    expect(typeof Drupal.behaviors.biolandComponentMenuForm.attach).toBe('function');
  });

  test('prefills title and link from the picked component', () => {
    buildForm();
    Drupal.behaviors.biolandComponentMenuForm.attach(document);

    pick('bioland_component', 'bl2-component-forums');

    expect(el('title[0][value]').value).toBe('Forums');
    expect(el('link[0][uri]').value).toBe('<nolink>');
  });

  test('prefills title from the content type once one is picked', () => {
    buildForm();
    Drupal.behaviors.biolandComponentMenuForm.attach(document);

    pick('bioland_component', 'bl2-component-content-type');
    expect(el('title[0][value]').value).toBe('');

    pick('bioland_component_content_type', 'news');
    expect(el('title[0][value]').value).toBe('News');
  });

  test('a re-pick replaces its own prefill', () => {
    buildForm();
    Drupal.behaviors.biolandComponentMenuForm.attach(document);

    pick('bioland_component', 'bl2-component-content-type');
    pick('bioland_component_content_type', 'news');
    pick('bioland_component_content_type', 'event');

    expect(el('title[0][value]').value).toBe('Events');
  });

  test('never clobbers what the editor typed', () => {
    buildForm();
    Drupal.behaviors.biolandComponentMenuForm.attach(document);

    el('title[0][value]').value = 'Latest News';
    el('link[0][uri]').value = 'internal:/reports';
    pick('bioland_component', 'bl2-component-forums');

    expect(el('title[0][value]').value).toBe('Latest News');
    expect(el('link[0][uri]').value).toBe('internal:/reports');
  });

  test('attaches once per form and only on Component-mode forms', () => {
    document.body.innerHTML = '<form class="menu-link-content-form"><select name="bioland_component"></select></form>';
    Drupal.behaviors.biolandComponentMenuForm.attach(document);

    const form = buildForm();
    Drupal.behaviors.biolandComponentMenuForm.attach(document);
    Drupal.behaviors.biolandComponentMenuForm.attach(document);
    expect(form.hasAttribute('data-once-bioland-component-menu-form')).toBe(true);
  });
});
