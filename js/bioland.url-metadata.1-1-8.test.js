/**
 * @file
 * BL-1191: tests for the Related websites title/description fill.
 */

describe('Bioland URL metadata fill', () => {
  const ENDPOINT = '/bioland/url-metadata?token=abc';
  const settings = {bioland: {urlMetadataEndpoint: ENDPOINT, relatedWebsitesTid: 13}};
  let editor;

  function buildForm(typeValue = '13') {
    document.body.innerHTML = `
      <form>
        <select id="edit-field-type-placement"><option value="5">Project</option><option value="13">Related websites</option></select>
        <input type="text" name="title[0][value]">
        <input type="url" name="field_url[0][uri]">
        <textarea name="body[0][value]" data-ckeditor5-id="ck1"></textarea>
      </form>`;
    document.querySelector('#edit-field-type-placement').value = typeValue;
    Drupal.behaviors.biolandUrlMetadata.attach(document, settings);
    return document.querySelector('form');
  }

  function leaveUrl(value) {
    const input = document.querySelector('input[name="field_url[0][uri]"]');
    input.value = value;
    input.dispatchEvent(new Event('change'));
  }

  const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    let data = '';
    editor = {getData: jest.fn(() => data), setData: jest.fn((v) => { data = v; })};
    global.Drupal = {behaviors: {}, CKEditor5Instances: new Map([['ck1', editor]])};
    global.once = (id, selector, context) => {
      const attribute = 'data-once-' + id;
      return Array.from((context || document).querySelectorAll(selector))
        .filter((element) => !element.hasAttribute(attribute))
        .map((element) => {
          element.setAttribute(attribute, '');
          return element;
        });
    };
    global.fetch = jest.fn(() => Promise.resolve({
      ok: true,
      json: () => Promise.resolve({title: 'CBD', description: 'Biodiversity & people'}),
    }));
    jest.resetModules();
    require('./bioland-url-metadata-1-1-8.js');
  });

  test('fills empty title and body from the looked-up site', async () => {
    buildForm();
    leaveUrl('https://www.cbd.int/');
    await flush();
    expect(fetch).toHaveBeenCalledWith(ENDPOINT + '&url=' + encodeURIComponent('https://www.cbd.int/'), expect.objectContaining({credentials: 'same-origin'}));
    expect(document.querySelector('input[name="title[0][value]"]').value).toBe('CBD');
    expect(editor.setData).toHaveBeenCalledWith('<p>Biodiversity &amp; people</p>');
  });

  test('never overwrites what the editor typed', async () => {
    buildForm();
    document.querySelector('input[name="title[0][value]"]').value = 'My own title';
    editor.setData('<p>Mine</p>');
    editor.setData.mockClear();
    leaveUrl('https://www.cbd.int/');
    await flush();
    expect(document.querySelector('input[name="title[0][value]"]').value).toBe('My own title');
    expect(editor.setData).not.toHaveBeenCalled();
  });

  test('replaces its own previous fill when the URL changes', async () => {
    buildForm();
    leaveUrl('https://www.cbd.int/');
    await flush();
    fetch.mockImplementationOnce(() => Promise.resolve({ok: true, json: () => Promise.resolve({title: 'BCH', description: ''})}));
    leaveUrl('https://bch.cbd.int/');
    await flush();
    expect(document.querySelector('input[name="title[0][value]"]').value).toBe('BCH');
  });

  test('skips other content types, invalid URLs and repeat values', async () => {
    buildForm('5');
    leaveUrl('https://www.cbd.int/');
    buildForm();
    leaveUrl('not a url');
    leaveUrl('javascript:alert(1)');
    expect(fetch).not.toHaveBeenCalled();
    leaveUrl('https://www.cbd.int/');
    leaveUrl('https://www.cbd.int/');
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  test('ignores a failed lookup and a stale response', async () => {
    buildForm();
    fetch.mockImplementationOnce(() => Promise.resolve({ok: false}));
    leaveUrl('https://down.example/');
    await flush();
    expect(document.querySelector('input[name="title[0][value]"]').value).toBe('');

    const input = document.querySelector('input[name="field_url[0][uri]"]');
    leaveUrl('https://www.cbd.int/');
    input.value = 'https://newer.example/';
    await flush();
    expect(document.querySelector('input[name="title[0][value]"]').value).toBe('');
  });

  test('does nothing without an endpoint', () => {
    document.body.innerHTML = '<input type="url" name="field_url[0][uri]">';
    Drupal.behaviors.biolandUrlMetadata.attach(document, {bioland: {}});
    expect(document.querySelector('input').hasAttribute('data-once-bioland-url-metadata')).toBe(false);
  });
});
