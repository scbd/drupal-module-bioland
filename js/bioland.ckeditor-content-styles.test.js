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
    // which this file must out-specify per the header comment.
    expect(css).not.toMatch(
      /(?<!\.ck)\.ck-content\s+\.drupal-media\s+iframe\.media-oembed-content/
    );
  });
});
