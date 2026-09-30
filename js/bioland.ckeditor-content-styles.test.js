const fs = require('fs');
const path = require('path');

/**
 * @file
 * Contract test for css/bioland.ckeditor.css (BL-1207).
 *
 * This is a plain stylesheet, not a JS behavior, so there is no
 * Drupal.behaviors surface to exercise. Instead this pins the presence and
 * shape of the remote-video oEmbed rule as a static string/regex contract,
 * mirroring the parsing idiom in bioland.config-schema.test.js.
 */

function readStylesheet() {
  return fs.readFileSync(
    path.join(__dirname, '../css/bioland.ckeditor.css'),
    'utf8'
  );
}

describe('Bioland CKEditor content styles - remote video width (BL-1207)', () => {
  let css;

  beforeAll(() => {
    css = readStylesheet();
  });

  test('scopes the remote video rule to .ck.ck-content .drupal-media iframe.media-oembed-content', () => {
    expect(css).toMatch(
      /\.ck\.ck-content\s+\.drupal-media\s+iframe\.media-oembed-content\s*\{/
    );
  });

  test('makes the media widget holding an oEmbed iframe full width (core styles it display: table)', () => {
    const match = css.match(
      /\.ck\.ck-content\s+\.drupal-media:has\(iframe\.media-oembed-content\)\s*\{([^}]*)\}/
    );
    expect(match).not.toBeNull();
    expect(match[1]).toMatch(/width:\s*100%/);
  });

  test('sets the oEmbed iframe to full width with a preserved 16:9 aspect ratio', () => {
    const match = css.match(
      /\.ck\.ck-content\s+\.drupal-media\s+iframe\.media-oembed-content\s*\{([^}]*)\}/
    );
    expect(match).not.toBeNull();

    const body = match[1];
    expect(body).toMatch(/width:\s*100%/);
    expect(body).toMatch(/height:\s*auto/);
    expect(body).toMatch(/aspect-ratio:\s*16\s*\/\s*9/);
  });

  test('keeps the two-class .ck.ck-content scope (out-specifies CKEditor defaults)', () => {
    // Never regress to the single-class `.ck-content` CKEditor uses itself,
    // which this file must out-specify per the header comment. Every
    // occurrence of the unscoped selector text must actually be part of the
    // two-class scoped selector, i.e. the counts must match.
    const unscopedSelector = '.ck-content .drupal-media iframe.media-oembed-content';
    const scopedSelector = `.ck${unscopedSelector}`;

    const unscopedCount = css.split(unscopedSelector).length - 1;
    const scopedCount = css.split(scopedSelector).length - 1;

    expect(unscopedCount).toBeGreaterThan(0);
    expect(unscopedCount).toBe(scopedCount);
  });
});

describe('Bioland CKEditor content styles - embed frames (BL-1313)', () => {
  let css;

  beforeAll(() => {
    css = readStylesheet();
  });

  function ruleBody(selectorPattern) {
    const match = css.match(new RegExp(selectorPattern + '\\s*\\{([^}]*)\\}'));
    return match ? match[1] : null;
  }

  test('makes the media widget holding a non-oEmbed iframe full width', () => {
    const body = ruleBody(
      '\\.ck\\.ck-content\\s+\\.drupal-media:has\\(iframe:not\\(\\.media-oembed-content\\)\\)'
    );
    expect(body).not.toBeNull();
    expect(body).toMatch(/width:\s*100%/);
  });

  test('blocks the embed iframe, caps it at the widget and drops the border', () => {
    const body = ruleBody(
      '\\.ck\\.ck-content\\s+\\.drupal-media\\s+iframe:not\\(\\.media-oembed-content\\)'
    );
    expect(body).not.toBeNull();
    expect(body).toMatch(/display:\s*block/);
    expect(body).toMatch(/max-width:\s*100%/);
    expect(body).toMatch(/border:\s*0/);
  });

  test('leaves the embed iframe width and height to the behavior', () => {
    const body = ruleBody(
      '\\.ck\\.ck-content\\s+\\.drupal-media\\s+iframe:not\\(\\.media-oembed-content\\)'
    );
    expect(body).not.toMatch(/(^|[;\s])width:/);
    expect(body).not.toMatch(/(^|[;\s])height:/);
    expect(body).not.toMatch(/aspect-ratio/);
  });

  test('keeps the two-class .ck.ck-content scope on the embed rules', () => {
    const unscoped = '.ck-content .drupal-media iframe:not(.media-oembed-content)';
    expect(css.split(unscoped).length - 1).toBeGreaterThan(0);
    expect(css.split(unscoped).length).toBe(css.split(`.ck${unscoped}`).length);
  });
});
