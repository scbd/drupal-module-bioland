const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

function parseSchema(source) {
  // JSON_SCHEMA rejects executable tags in both js-yaml 3 and 4.
  return yaml.load(source, { schema: yaml.JSON_SCHEMA });
}

describe('Bioland configuration schema', () => {
  test('rejects JavaScript function tags', () => {
    expect(() => parseSchema('callback: !!js/function >\n  function () { return 1; }\n'))
      .toThrow(/unknown tag/);
  });

  test('rejects duplicate mapping keys', () => {
    expect(() => parseSchema('duplicate: 1\nduplicate: 2\n'))
      .toThrow(/duplicated mapping key/);
  });

  test('strictly parses the schema and preserves the merged settings', () => {
    // Do not enable json mode: duplicate mapping keys must fail, as in Drupal.
    const schema = parseSchema(fs.readFileSync(
      path.join(__dirname, '../config/schema/bioland.schema.yml'),
      'utf8'
    ));
    const settings = schema['bioland.settings'].mapping;

    expect(settings.component_menu_add_enabled).toEqual({
      type: 'boolean',
      label: 'Enable Mega Menu components'
    });
    expect(settings.component_menu_show_attributes).toEqual({
      type: 'boolean',
      label: 'Show the Attributes section on component menu link forms'
    });
    expect(settings.field_visibility_rules.type).toBe('text');
    expect(settings.google_analytics_ids.type).toBe('string');
    expect(settings.mega_menu.type).toBe('mapping');
    expect(settings.theme.type).toBe('mapping');
  });
});
