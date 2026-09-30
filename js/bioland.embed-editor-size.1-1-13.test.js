const fs = require('fs');
const path = require('path');

/**
 * @file
 * Unit tests for bioland-embed-editor-size-1-1-13.js (BL-1313).
 *
 * The editor DOM is built from the real body markup of node 10076
 * (tests/fixtures/processed-body-node-10076.html): media 10127 (field
 * 75% x 50%) and media 10126 (field 100% x 75%), each wrapped the way
 * CKEditor 5 wraps a media preview (.drupal-media widget).
 */

const FIXTURE = fs.readFileSync(
  path.join(__dirname, '../tests/fixtures/processed-body-node-10076.html'),
  'utf8'
);

// Wraps every rendered media element of the fixture in a CKEditor media
// widget, optionally carrying a CKEditor resize.
function editorBody(resize) {
  const holder = document.createElement('div');
  holder.innerHTML = FIXTURE;
  holder.querySelectorAll('div.media').forEach((media, index) => {
    const widget = document.createElement('div');
    widget.className = 'ck-widget drupal-media';
    const size = (resize || [])[index] || {};
    if (size.width) widget.setAttribute('data-media-width', size.width);
    if (size.height) widget.setAttribute('data-media-height', size.height);
    if (size.width) widget.style.width = size.width;
    media.replaceWith(widget);
    widget.appendChild(media);
  });
  return holder.innerHTML;
}

function buildEditor(resize, extra) {
  document.body.innerHTML = `
    <form id="node-content-edit-form">
      <div class="ck ck-editor"><div class="ck ck-editor__main">
        <div class="ck ck-content ck-editor__editable">${editorBody(resize)}${extra || ''}</div>
      </div></div>
    </form>`;
}

// Mirrors the ckeditor_media_resizer 1.0.3 form: unit buttons whose click
// runs _setUnit() (selects, clears both inputs), then width and height.
function buildBalloonForm(values) {
  const form = document.createElement('div');
  form.className = 'ck ck-media-resize-form';
  const units = ['px', '%', 'em', 'vw', 'vh']
    .map((u) => `<button type="button" class="ck-media-resize-form__unit-btn${u === 'px' ? ' ck-on' : ''}" data-unit="${u}">${u}</button>`)
    .join('');
  form.innerHTML = `
    <div class="ck-media-resize-form__unit-row"><div class="ck-media-resize-form__unit-selector">${units}</div></div>
    <div class="ck-media-resize-form__row">
      <div class="ck ck-labeled-field-view ck-labeled-field-view_empty"><input type="number" class="ck ck-input ck-input-text"></div>
      <button class="ck-media-resize-form__lock"></button>
      <div class="ck ck-labeled-field-view ck-labeled-field-view_empty"><input type="number" class="ck ck-input ck-input-text"></div>
    </div>`;
  const inputs = form.querySelectorAll('input');
  form.querySelectorAll('.ck-media-resize-form__unit-btn').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelectorAll('.ck-media-resize-form__unit-btn').forEach((b) => b.classList.toggle('ck-on', b === button));
      inputs[0].value = '';
      inputs[1].value = '';
    });
  });
  inputs.forEach((input) => {
    input.addEventListener('input', () => {
      input.parentNode.classList.toggle('ck-labeled-field-view_empty', !input.value);
    });
  });
  if (values) {
    inputs[0].value = values.width || '';
    inputs[1].value = values.height || '';
  }
  return form;
}

function openBalloon(values) {
  let wrapper = document.querySelector('.ck-body-wrapper');
  if (!wrapper) {
    wrapper = document.createElement('div');
    wrapper.className = 'ck-body-wrapper';
    document.body.appendChild(wrapper);
  }
  const form = buildBalloonForm(values);
  wrapper.appendChild(form);
  return form;
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function frames() {
  return Array.from(document.querySelectorAll('.drupal-media iframe'));
}

function selectWidget(index) {
  document.querySelectorAll('.drupal-media')[index].classList.add('ck-widget_selected');
}

function activeUnit(form) {
  return form.querySelector('.ck-media-resize-form__unit-btn.ck-on').dataset.unit;
}

describe('Bioland Embed Editor Size (BL-1313)', () => {
  let behavior;

  beforeEach(() => {
    document.body.innerHTML = '';
    global.once = (id, selector, context) => {
      const attribute = 'data-once-' + id;
      const elements = typeof selector === 'string'
        ? Array.from((context || document).querySelectorAll(selector))
        : [selector];
      return elements
        .filter((element) => !element.hasAttribute(attribute))
        .map((element) => {
          element.setAttribute(attribute, '');
          return element;
        });
    };
    global.once.remove = (id, element) => {
      element.removeAttribute('data-once-' + id);
      return [element];
    };
    jest.resetModules();
    require('./bioland-embed-editor-size-1-1-13.js');
    behavior = Drupal.behaviors.biolandEmbedEditorSize;
  });

  afterEach(() => {
    behavior.detach(document, {}, 'unload');
  });

  describe('sizeEmbedPreview()', () => {
    function frame(attributes, resize) {
      const widget = document.createElement('div');
      widget.className = 'drupal-media';
      Object.entries(resize || {}).forEach(([key, value]) => widget.setAttribute('data-media-' + key, value));
      const iframe = document.createElement('iframe');
      Object.entries(attributes || {}).forEach(([key, value]) => iframe.setAttribute(key, value));
      widget.appendChild(iframe);
      return iframe;
    }

    test.each([
      ['field px + px is a ratio', { width: '800', height: '450' }, null, 'aspect-ratio: 800 / 450; width: 100%;'],
      ['field px suffix + px is a ratio', { width: '800px', height: '450px' }, null, 'aspect-ratio: 800 / 450; width: 100%;'],
      ['CKEditor px + px is a ratio', { width: '100%', height: '75%' }, { width: '800px', height: '450px' }, 'aspect-ratio: 800 / 450; width: 100%;'],
      ['missing height is 16:9 at the width', { width: '75%' }, null, 'aspect-ratio: 16 / 9; width: 75%;'],
      ['missing everything is 16:9 full width', {}, null, 'aspect-ratio: 16 / 9; width: 100%;'],
      ['px width, missing height', { width: '640' }, null, 'aspect-ratio: 16 / 9; width: 640px;'],
      ['% height becomes vh', { width: '100%', height: '75%' }, null, 'width: 100%; height: 75vh;'],
      ['field 75% x 50% (media 10127)', { width: '75%', height: '50%' }, null, 'width: 75%; height: 50vh;'],
      ['vh height is kept', { width: '100%', height: '60vh' }, null, 'width: 100%; height: 60vh;'],
      ['% height is capped at 100vh', { width: '100%', height: '150%' }, null, 'width: 100%; height: 100vh;'],
      ['vh height is capped at 100vh', { width: '100%', height: '250vh' }, null, 'width: 100%; height: 100vh;'],
      ['px height with a % width', { width: '100%', height: '450' }, null, 'width: 100%; height: 450px;'],
      ['em height', { width: '100%', height: '30em' }, null, 'width: 100%; height: 30em;'],
      ['rem height', { width: '50vw', height: '20rem' }, null, 'width: 50vw; height: 20rem;'],
      ['vw height', { width: '100%', height: '40vw' }, null, 'width: 100%; height: 40vw;'],
      ['CKEditor % width fills the wrapper, never twice', { width: '75%', height: '50%' }, { width: '50%', height: '40%' }, 'width: 100%; height: 40vh;'],
      ['CKEditor % width alone keeps the field height', { width: '75%', height: '50%' }, { width: '50%' }, 'width: 100%; height: 50vh;'],
      ['CKEditor height alone keeps the field width', { width: '100%', height: '75%' }, { height: '40%' }, 'width: 100%; height: 40vh;'],
      ['mixed sources never form a ratio', { width: '100%', height: '450' }, { width: '800px' }, 'width: 800px; height: 450px;'],
      ['CKEditor px width with no height anywhere', { width: '100%' }, { width: '800px' }, 'aspect-ratio: 16 / 9; width: 800px;'],
      ['invalid values fall back', { width: '100%;background:url(x)', height: 'expression(alert(1))' }, null, 'aspect-ratio: 16 / 9; width: 100%;'],
      ['invalid CKEditor values fall through to the field', { width: '75%', height: '50%' }, { width: 'calc(1px)', height: '0' }, 'width: 75%; height: 50vh;'],
      ['oversized numbers are dropped', { width: '99999', height: '75%' }, null, 'width: 100%; height: 75vh;'],
    ])('%s', (name, attributes, resize, expected) => {
      expect(behavior.sizeEmbedPreview(frame(attributes, resize))).toBe(expected);
    });
  });

  describe('editor preview', () => {
    test('sizes both embed previews of node 10076 from the frame field', () => {
      buildEditor();
      behavior.attach(document, {});

      const [powerBi, flowChart] = frames();
      expect(powerBi.getAttribute('style')).toBe('width: 75%; height: 50vh;');
      expect(flowChart.getAttribute('style')).toBe('width: 100%; height: 75vh;');
      expect(powerBi.hasAttribute('data-bioland-embed-sized')).toBe(true);
    });

    test('a saved CKEditor % size wins and the wrapper keeps its width', () => {
      buildEditor([null, { width: '50%', height: '40%' }]);
      behavior.attach(document, {});

      const flowChart = frames()[1];
      expect(flowChart.getAttribute('style')).toBe('width: 100%; height: 40vh;');
      expect(flowChart.closest('.drupal-media').style.width).toBe('50%');
    });

    test('a CKEditor px + px size is a ratio and the wrapper px width is cleared', () => {
      buildEditor([{ width: '800px', height: '450px' }]);
      behavior.attach(document, {});

      const powerBi = frames()[0];
      expect(powerBi.getAttribute('style')).toBe('aspect-ratio: 800 / 450; width: 100%;');
      expect(powerBi.closest('.drupal-media').style.width).toBe('');
    });

    test('a CKEditor px width alone keeps the wrapper width', () => {
      buildEditor([{ width: '800px' }]);
      behavior.attach(document, {});

      expect(frames()[0].closest('.drupal-media').style.width).toBe('800px');
    });

    test('re-sizes when the editor applies a resize', async () => {
      buildEditor();
      behavior.attach(document, {});
      const flowChart = frames()[1];
      const widget = flowChart.closest('.drupal-media');

      widget.setAttribute('data-media-width', '50%');
      widget.setAttribute('data-media-height', '40%');
      await flush();
      expect(flowChart.getAttribute('style')).toBe('width: 100%; height: 40vh;');

      // The resizer re-applies the px width after every model change.
      widget.setAttribute('data-media-width', '800px');
      widget.setAttribute('data-media-height', '450px');
      widget.style.width = '800px';
      await flush();
      expect(flowChart.getAttribute('style')).toBe('aspect-ratio: 800 / 450; width: 100%;');
      expect(widget.style.width).toBe('');
      widget.style.width = '800px';
      await flush();
      expect(widget.style.width).toBe('');
    });

    test('sizes a preview that arrives after attach', async () => {
      document.body.innerHTML = '<div class="ck ck-content"></div>';
      behavior.attach(document, {});
      const holder = document.createElement('div');
      holder.innerHTML = editorBody();
      document.querySelector('.ck-content').appendChild(holder);
      await flush();

      expect(frames()[0].getAttribute('style')).toBe('width: 75%; height: 50vh;');
    });

    test('sizes an editable CKEditor mounts after attach', async () => {
      document.body.innerHTML = '<form id="node-content-edit-form"></form>';
      behavior.attach(document, {});
      const editable = document.createElement('div');
      editable.className = 'ck ck-content';
      editable.innerHTML = editorBody();
      document.querySelector('form').appendChild(editable);
      await flush();

      expect(frames()[1].getAttribute('style')).toBe('width: 100%; height: 75vh;');
    });

    test('settles without a mutation loop', async () => {
      buildEditor([{ width: '800px', height: '450px' }]);
      const records = [];
      const spy = new MutationObserver((list) => records.push(...list));
      spy.observe(document.body, { attributes: true, subtree: true, childList: true });
      behavior.attach(document, {});
      await flush();
      await flush();
      const settled = records.length;
      await flush();
      await flush();
      spy.disconnect();

      expect(records.length).toBe(settled);
    });

    test('leaves oEmbed players and images alone', () => {
      buildEditor(null, `
        <div class="drupal-media ck-widget"><div class="media media--type-remote-video"><iframe class="media-oembed-content" width="200" height="113"></iframe></div></div>
        <div class="drupal-media ck-widget" data-media-width="50%"><div class="media media--type-image"><img src="/a.jpg" width="400" height="300"></div></div>`);
      behavior.attach(document, {});

      const player = document.querySelector('iframe.media-oembed-content');
      expect(player.hasAttribute('style')).toBe(false);
      expect(player.hasAttribute('data-bioland-embed-sized')).toBe(false);
      expect(document.querySelector('img').hasAttribute('style')).toBe(false);
    });

    test('ignores iframes outside an embed media', () => {
      buildEditor(null, '<div class="drupal-media"><div class="media media--type-document"><iframe width="100%" height="75%"></iframe></div></div>');
      behavior.attach(document, {});

      expect(document.querySelector('.media--type-document iframe').hasAttribute('style')).toBe(false);
    });

    test('once() prevents a double attach', () => {
      buildEditor();
      const Original = global.MutationObserver;
      let created = 0;
      global.MutationObserver = class extends Original {
        constructor(callback) {
          super(callback);
          created += 1;
        }
      };
      try {
        behavior.attach(document, {});
        const first = created;
        behavior.attach(document, {});
        behavior.attach(document.querySelector('form'), {});

        expect(first).toBe(2);
        expect(created).toBe(first);
        expect(document.querySelectorAll('[data-once-bioland-embed-editor-size]').length).toBe(1);
      } finally {
        global.MutationObserver = Original;
      }
    });

    test('detach on unload disconnects and allows a later attach', async () => {
      buildEditor();
      behavior.attach(document, {});
      behavior.detach(document, {}, 'unload');
      const flowChart = frames()[1];
      flowChart.closest('.drupal-media').setAttribute('data-media-height', '40%');
      await flush();
      expect(flowChart.getAttribute('style')).toBe('width: 100%; height: 75vh;');

      behavior.attach(document, {});
      expect(flowChart.getAttribute('style')).toBe('width: 100%; height: 40vh;');
    });
  });

  describe('Media Resize balloon prefill', () => {
    test('fills % 100 / 75 from the frame field when the balloon opens empty', async () => {
      buildEditor();
      behavior.attach(document, {});
      selectWidget(1);
      const form = openBalloon();
      await flush();

      const [width, height] = form.querySelectorAll('input');
      expect(activeUnit(form)).toBe('%');
      expect(width.value).toBe('100');
      expect(height.value).toBe('75');
      expect(width.parentNode.classList.contains('ck-labeled-field-view_empty')).toBe(false);
      expect(height.parentNode.classList.contains('ck-labeled-field-view_empty')).toBe(false);
    });

    test('fills 75 / 50 for media 10127', async () => {
      buildEditor();
      behavior.attach(document, {});
      selectWidget(0);
      const form = openBalloon();
      await flush();

      const [width, height] = form.querySelectorAll('input');
      expect(width.value).toBe('75');
      expect(height.value).toBe('50');
    });

    test('never overwrites a saved CKEditor value', async () => {
      buildEditor([null, { width: '50%', height: '40%' }]);
      behavior.attach(document, {});
      selectWidget(1);
      const form = openBalloon({ width: '50', height: '40' });
      await flush();

      const [width, height] = form.querySelectorAll('input');
      expect(width.value).toBe('50');
      expect(height.value).toBe('40');
    });

    test('leaves a half-filled form alone', async () => {
      buildEditor();
      behavior.attach(document, {});
      selectWidget(1);
      const form = openBalloon({ width: '30' });
      await flush();

      const [width, height] = form.querySelectorAll('input');
      expect(width.value).toBe('30');
      expect(height.value).toBe('');
    });

    test('fills the width only when the units differ', async () => {
      buildEditor(null, '<div class="drupal-media"><div class="media media--type-embed"><iframe width="640" height="75%"></iframe></div></div>');
      behavior.attach(document, {});
      selectWidget(2);
      const form = openBalloon();
      await flush();

      const [width, height] = form.querySelectorAll('input');
      expect(activeUnit(form)).toBe('px');
      expect(width.value).toBe('640');
      expect(height.value).toBe('');
    });

    test('does nothing for an image or oEmbed selection', async () => {
      buildEditor(null, '<div class="drupal-media"><div class="media media--type-remote-video"><iframe class="media-oembed-content" width="200" height="113"></iframe></div></div>');
      behavior.attach(document, {});
      selectWidget(2);
      const form = openBalloon();
      await flush();

      expect(activeUnit(form)).toBe('px');
      expect(form.querySelector('input').value).toBe('');
    });

    test('prefills again on every open', async () => {
      buildEditor();
      behavior.attach(document, {});
      selectWidget(1);
      openBalloon().remove();
      await flush();
      const form = openBalloon();
      await flush();

      expect(form.querySelectorAll('input')[1].value).toBe('75');
    });
  });
});
