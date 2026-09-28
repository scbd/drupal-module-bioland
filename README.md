# Bioland Module

Bioland is a comprehensive Drupal module that provides enhanced field management functionality and translation default creation capabilities, originally adapted from the SCBD thesaurus tags module. It offers four main functionalities that can be individually enabled or disabled through the administration interface, alongside a front-end settings suite (mega menu, home page, home widgets, theme) and a mega-menu component authoring surface. See `docs/architecture.md`, `docs/prd.md`, and `docs/adr/` for the code-checked, kept-current record of the whole module; this file focuses on the original field/translation feature set below plus a summary of the newer front-end features.

## Features

### 1. Field Visibility Control

- **Purpose**: Show/hide form fields based on content type selection
- **Controls**: URL field, date fields (start/end), and published field visibility
- **Behavior**: Automatically hides/shows relevant fields when users select different content types
- **Setting**: `enable_field_visibility`

### 2. Additional Fields

- **Purpose**: Add content-type specific additional fields based on thesaurus content type
- **Supports**: Vue.js-based dynamic field addition
- **Content Type Mapping**:

  - Type 3 (Events): Event statuses
  - Type 5 (Projects): Project statuses, geographical scopes
  - Type 8 (Ministry): Organization types, government types
  - Type 9: Ecosystem types
  - Type 12: Document types

- **Setting**: `enable_additional_fields`

### 3. Auto Summary

- **Purpose**: Automatically generate summary from body content as users type
- **Features**:
  - Smart text truncation with sentence boundary preservation
  - HTML tag stripping
  - Internationalization support with Intl.Segmenter
  - Fallback for older browsers
- **Triggers**: On body field changes, keydown events, and mouseout
- **Setting**: `enable_auto_summary`

### 4. Translation Default Creation

- **Purpose**: Automatically create translation defaults (in all configured languages) for translatable entities
- **Features**:
  - Creates translation defaults when entities are created or updated
  - Works with any translatable entity type
  - Configurable target languages (all languages or specific selection)
  - Optional copying of translatable field values to translation defaults
  - Batch operations for processing existing entities
- **Setting**: `translation.auto_create`

### 5. Front-End Settings (Mega Menu, Home Page, Home Widgets, Theme)

- **Purpose**: Configure the navigation, homepage, and branding that the separate bioland-head
  Nuxt front end renders
- **Location**: `/admin/config/bioland/settings/front-end` and its subsections (general,
  mega menu, home page, home widgets, theme)
- **Mega Menu tab** (`BiolandMegaMenuForm`, config under `mega_menu.*`): per-content-type-term menu
  limits and position, a "Content Types Statistics Menu" section, and additional menus (country
  profiles, focal points, national targets, national report, BCH, ABSCH, forums). BSL sites only
  expose the content-type-menus section.
- **Theme tab** (`BiolandThemeForm`, config under `theme.*`, see
  `docs/adr/0006-theme-authority.md`): the per-site theme authoring surface — primary/secondary
  colour, secondary background colour, home-page widget columns, mega-menu max columns/rows-per-column/
  horizontal-card-max, and the language-bar wrap threshold. Fields are lazily pre-filled from DMSM's
  per-network theme document (`BiolandDmsmConfigService::getEffectiveTheme()`) but nothing is written
  to config until an editor submits the form. `color.primary` also drives the CKEditor content-style
  heading underline (see below) and the mega-menu "Show Arrow" preview colour, with per-flavour
  (bl2/BSL) fallback colours defined on `BiolandThemeContract` when no theme has been authored yet.
- **CKEditor content styles**: `css/bioland.ckeditor.css` aligns in-editor heading rendering with the
  public Nuxt front end (heading sizes and a primary-coloured underline), reading `--bs-primary` with
  a bl2 default when no site colour is available.

### 6. Mega Menu Component Authoring

- **Purpose**: Let editors turn a menu link into a Bioland mega-menu component from a picker instead
  of hand-typing `bl2-component-*` classes into the class field `menu_link_attributes` provides. See
  `docs/adr/0005-component-menu-authoring-surface.md`.
- **"Add Mega Menu component" flow**: a dedicated route,
  `/admin/structure/menu/manage/{menu}/add-component`, sibling to core's own "Add link" route, gated
  by the `_bioland_component_menu_enabled` access check (`BiolandComponentMenuAccessCheck`), which is
  itself controlled by the site-wide mega-menu toggle in Bioland's admin settings.
- **Component picker**: `BiolandComponentRegistry` supplies the canonical list of `bl2-` prefixed
  component tokens; `BiolandComponentMenuFormMode` resolves per-component presentation controls
  (columns, "Show Arrow", thumbnails, column width) and injects them into the menu link form.
- **Menu overview indicator**: `BiolandComponentMenuOverview` adds a column to the menu link overview
  screen showing which links are mega-menu components.
- **Admin toggles**: `component_menu_add_enabled` (on by default; turns the whole add-component flow
  off) and `component_menu_show_attributes` (off by default; reveals the raw Attributes fieldset
  `menu_link_attributes` normally hides on the component form).
- **Retired**: the Theme tab's "Show Forums in the mega menu" checkbox (`theme.mega_menu.forums`) has
  been removed; the key is dropped from `BiolandThemeContract::KEYS` and the config schema, and
  update hook 9079 clears it from any site that saved it while the checkbox still existed.

## Configuration

Navigate to `/admin/config/bioland/settings` to configure the module.

### Configuration Object Structure

The module stores configuration in `bioland.settings` with the following structure:

```php
[
  '_core' => [
    'default_config_hash' => '...',
  ],
  
  // Site Identity
  'countries' => ['gt', 'us', 'fr'],           // Array of ISO country codes
  'region' => 'south_america',                 // Geographic region
  'is_biosafety_land' => true,                 // Site type flag
  
  // Feature Toggles
  'enable_field_visibility' => true,           // Show/hide fields based on rules
  'enable_additional_fields' => true,          // Vue.js additional fields
  'enable_auto_summary' => true,               // Auto-generate summaries
  'enable_help_comments' => true,              // Field help text feature
  'field_visibility_rules' => '',              // JSON rules (see below)
  
  // Translation Configuration (nested object)
  'translation' => [
    'auto_create' => true,                     // Auto-create translations
    'use_all_languages' => true,               // Use all vs. target_languages
    'target_languages' => [],                  // Specific language codes
    'copy_source_values' => true,              // Copy field values
    'entity_types' => [],                      // Content types to translate
  ],
  
  // Localization
  'default_locale' => 'en',                    // Primary language
  'enabled_locales' => [                       // All active languages
    'ar', 'zh', 'en', 'fr', 'ru', 'es'
  ],
]
```

### TypeScript/JavaScript Interface

```typescript
interface BiolandSettings {
  // Core
  _core?: {
    default_config_hash: string;
  };
  
  // Site Identity
  countries: string[];              // ISO country codes (e.g., ['gt', 'us', 'fr'])
  region: string;                   // e.g., 'south_america', 'europe', 'asia'
  is_biosafety_land: boolean;       // Site type flag
  
  // Field Functionality Features
  enableFieldVisibility: boolean;
  enableAdditionalFields: boolean;
  enableAutoSummary: boolean;
  enableHelpComments: boolean;
  fieldVisibilityRules: string;     // JSON string (see Field Visibility Rules)
  
  // Translation Configuration (nested object)
  translation: {
    auto_create: boolean;           // Auto-create translations on entity save
    use_all_languages: boolean;     // Use all enabled languages vs. target_languages
    target_languages: string[];     // Specific language codes if not using all
    copy_source_values: boolean;    // Copy source field values to translations
    entity_types: string[];         // Content types to auto-translate
  };
  
  // Localization
  default_locale: string;           // e.g., 'en', 'es', 'fr'
  enabled_locales: string[];        // All enabled language codes
  
  // Batch Processing (form-only, not persisted)
  batchContentTypes?: string[];
  batchLimit?: number;
}
```

### General Settings

- **Countries**: Enter one or more ISO country codes (e.g., `gt`, `us`, `fr`)
- **Region**: Choose the geographical region
- **Is Biosafety Land**: Flag indicating site type

### Field Behavior Settings

- **Enable Field Visibility Control**: Toggle field show/hide functionality
- **Enable Additional Fields**: Toggle content-type specific additional fields
- **Enable Auto Summary**: Toggle automatic summary generation
- **Enable Help Comments**: Toggle field help text functionality
- **Field Visibility Rules**: Custom JSON rules for conditional field display (see below)

### Translation Default Settings

- **Automatically create translation defaults**: Enable/disable translation default creation
- **Use all available languages**: When enabled, creates translation defaults for all languages installed on the site
- **Target languages**: Select specific languages to create translation defaults for (only used when "Use all available languages" is disabled)
- **Copy source field values**: Whether to copy translatable field values from source to translation defaults
- **Entity types**: Select which entity types should have automatic translation defaults
- **Batch operations**: Process existing entities to create missing translation defaults

## Field Visibility Rules

The `field_visibility_rules` setting accepts JSON to define conditional field display logic based on content type and field values.

### JSON Structure

```json
{
  "3": {
    "field_event_registration_link": {
      "conditions": [
        {
          "field": "field_registration_required",
          "value": true,
          "operator": "equals"
        }
      ],
      "action": "show"
    },
    "field_event_venue_address": {
      "conditions": [
        {
          "field": "field_event_format",
          "value": ["in-person", "hybrid"],
          "operator": "in"
        }
      ],
      "action": "show"
    },
    "field_event_catering_notes": {
      "conditions": [
        {
          "field": "field_event_format",
          "value": "in-person",
          "operator": "equals"
        },
        {
          "field": "field_attendee_count",
          "value": 10,
          "operator": "greater_than"
        }
      ],
      "action": "show",
      "logic": "AND"
    }
  },
  "5": {
    "field_project_budget": {
      "conditions": [
        {
          "field": "field_project_status",
          "value": "approved",
          "operator": "equals"
        }
      ],
      "action": "show"
    }
  }
}
```

### Rule Definition

```typescript
interface FieldVisibilityRules {
  [contentTypeId: string]: {
    [targetFieldName: string]: FieldRule;
  };
}

interface FieldRule {
  conditions: Condition[];
  action: 'show' | 'hide';
  logic?: 'AND' | 'OR';  // Default: 'AND'
}

interface Condition {
  field: string;           // Field machine name to watch
  value: any;              // Value to compare (string, number, boolean, array)
  operator: OperatorType;
}

type OperatorType = 
  | 'equals'          // Exact match
  | 'not_equals'      // Not equal
  | 'contains'        // String contains
  | 'not_contains'    // String doesn't contain
  | 'in'              // Value in array
  | 'not_in'          // Value not in array
  | 'greater_than'    // Numeric >
  | 'less_than'       // Numeric <
  | 'empty'           // Field is empty/null
  | 'not_empty';      // Field has value
```

### Content Type IDs

- `3` - Events
- `5` - Projects
- `8` - Organizations
- `9` - Ecosystems
- `12` - Documents

### How It Works

1. **Parse on load**: `JSON.parse(biolandSettings.fieldVisibilityRules || '{}')`
2. **Watch trigger fields**: Attach change listeners to all `conditions[].field` values
3. **Evaluate conditions**: When trigger field changes, evaluate all conditions
4. **Apply action**: Show/hide target field wrapper based on result

**Key behavior**:

- Default state: All fields visible (unless explicitly hidden by rules)
- Multiple rules for same field: Last matching rule wins
- Invalid JSON: Feature silently fails (check browser console for errors)
- Multiple conditions: Combined with `logic` ('AND' or 'OR', default 'AND')

### Configuration Access

```php
// Read configuration
$config = \Drupal::config('bioland.settings');
$is_enabled = $config->get('enable_field_visibility');
$languages = $config->get('translation.target_languages') ?: [];
$countries = $config->get('countries') ?: [];

// Write configuration (editable)
$config = \Drupal::configFactory()->getEditable('bioland.settings');
$config->set('enable_auto_summary', FALSE);
$config->set('countries', ['gt', 'sv', 'hn']);
$config->set('translation.copy_source_values', TRUE);
$config->save();

// Via service (recommended)
$settings_manager = \Drupal::service('bioland.settings_manager');
$all_settings = $settings_manager->getAllSettings();
```

## Technical Details

### JavaScript Architecture

The module uses a modular JavaScript architecture:

- **js/bioland-field-visibility-1-1-12.js**: Handles field show/hide logic
- **js/bioland-additional-fields-1-1-12.js**: Manages Vue.js-based additional field mounting
- **js/bioland-auto-summary-1-1-12.js**: Provides intelligent summary generation
- **js/bioland-help-comments-1-1-12.js**: Renders translatable inline field help text
- **js/bioland-home-widgets-1-1-12.js**: Publishes per-country home-widget settings via `window.Bioland.homeWidgets`
- **js/bioland-component-menu-form-1-1-12.js**: Behaviours for the mega-menu component link form
- **js/bioland-language-redirect-1-1-12.js**: Language-redirect behaviour
- **js/bioland-hide-bulk-actions-1-1-12.js**: Hides selected bulk operations in admin listings
- **js/bioland-settings-toggle-1-1-12.js**: Show/hide behaviour for settings-form sections
- **js/bioland-debug-logger-1-1-12.js**: Shared opt-in debug logger used by the other behaviours

### Libraries

- **comprehensive_fields**: Includes all functionality
- **field_visibility**: Only field visibility functionality
- **additional_fields**: Only additional fields functionality
- **auto_summary**: Only auto summary functionality
- **admin**: Admin UI CSS for settings form

### Services

- **bioland.field_functionality_manager**: Manages settings and provides helper methods
- **bioland.settings_manager**: General settings management
- **bioland.translation_manager**: Handles translation defaults creation
- **bioland.translation_batch**: Processes batch translation operations
- **bioland.dmsm_config**: Fetches geography and the per-network theme document from the DMSM API
- **bioland.component_registry**: Canonical list of mega-menu `bl2-component-*` tokens
- **bioland.component_menu_access**: Route access check for the "Add Mega Menu component" flow
- **bioland.component_menu_form_mode**: Resolves per-component mega-menu presentation controls
- **bioland.component_menu_overview**: Drives the mega-menu indicator column on the menu overview screen

## Usage

### For Developers

The module attaches to the `node_content_form` (content type machine name `content`). JavaScript settings are passed via `drupalSettings.bioland`.

### For Content Editors

1. **Field Visibility**: Select content types and watch relevant fields appear/disappear
2. **Additional Fields**: Additional thesaurus-based fields will appear for supported content types
3. **Auto Summary**: Start typing in the body field and see the summary automatically populate

### Vue.js Integration

The additional fields functionality integrates with Vue.js applications. It expects:

- Global `Vue` object with `createApp` method
- Global `ScbdDrupalScbdFieldJs` object with application component

## Backward Compatibility

The module maintains backward compatibility with the original SCBD field module:

- Global `window.fieldVisibility` object
- Global `window.additionalFields` object
- Global `window.autoSummary` object
 

## Requirements

- Drupal 10/11 (`core_version_requirement: ^10 || ^11`)
- `menu_link_attributes` ≥ 1.7 (menu link class storage/UI, used by the mega-menu component flow)
- jQuery (provided by Drupal core)
- Vue.js (for additional fields functionality)
- Modern browser with JavaScript enabled

## Installation

1. Place the module in `/modules/custom/scbd-bioland`
2. Enable the module via Drupal admin or Drush
3. Configure settings at `/admin/config/bioland/settings`
4. Clear caches

### Installation Notes

- Requires a content type with machine name `content` (form id `node_content_form`). The installer will create it if missing.
- Defaults on fresh install:
  - Countries: `gt`
  - Region: `north_america`
  - Settings access: restricted by permission `administer bioland settings`, granted to the `administrator` role only by default.

## Document previews

Auto-generates a first-page WebP image for Document media (BL-1192), so the document listings
stop showing the generic grey placeholder for every document that has no manually chosen Image.

- **Env var**: `CONVERT_API_SECRET`, read via `getenv()` with a `Settings::get('bioland_convert_api_secret')`
  fallback (see `BiolandDocumentPreviewPolicy::resolveSecret()`). Never stored in config, never
  logged, never shown in a form; the settings form only shows a read-only "secret present: yes/no"
  indicator. Missing the secret silently disables the feature (no queue items, no conversions).
- **Toggle**: `bioland.settings.enable_document_preview` (default `TRUE`), on the System Functions tab.
- **When it runs**: external HTTP is never performed inside the editor's request. Saving a
  qualifying Document media item enqueues a `bioland_document_preview` queue item and remembers it;
  the service is tagged `needs_destruction`, so right after the response is flushed its `destruct()`
  performs the ConvertAPI call and fills the Image field (same shape as the BL-1191 website
  screenshot). A successful conversion deletes its queue item. The queue is the retry path only:
  `BiolandDocumentPreviewWorker` (cron, or `drush queue:run bioland_document_preview`) drains items
  that failed transiently. This matters because `bioland_update_9061` disables Drupal system cron
  (ADR 0004), so a cron-only design never produced an image on those sites.
  An editor-chosen Image is never overwritten, and an edit that does not replace the document file
  never triggers a new conversion.
- **PageRange=1 cost rule**: every ConvertAPI call is restricted to page 1 via the `PageRange`
  parameter — converting the whole document and discarding pages 2..n is billed per page and is
  never acceptable. A direct conversion billed for more than one page logs a warning so a
  regression is caught on staging.
- **Converters used** (re-verified live 2026-09-27 during the fix cycle, against
  `GET https://v2.convertapi.com/info/openapi/{ext}/to/{target}` for every allowed extension):
  - Direct (`{ext}/to/webp` exists, one call): `pdf`, `docx`, `pptx`, `xlsx`.
  - Via-pdf (two chained calls, `{ext}/to/pdf` then `pdf/to/webp`, the stored PDF never
    re-downloaded): `txt`, `rtf`, `odf`, `odg`, `odp`, `ods`, `odt`.
  - Via-office (two chained calls, `{ext}/to/{office}` then `{office}/to/webp`, the stored
    intermediate file never re-downloaded; `PageRange` is not documented on the first call for
    any of these six extensions, so it is applied only on the second): `doc`→`docx`, `ppt`→`pptx`,
    `xls`→`xlsx`, `key`→`pptx`, `numbers`→`xlsx`, `pages`→`docx`.
  - Dropped, no verified route (`webp`/`pdf`/`docx`/`pptx`/`xlsx` targets and, for the flat-ODF
    forms, their own un-flattened counterpart all returned 404): `fodt`, `fods`, `fodp`, `fodg`.
    An upload with one of these extensions is skipped at enqueue time with a notice-level log
    naming the extension; no queue item is created and no ConvertAPI call is made.
- **Manual pre-merge step**: `field_media_document` and `field_media_image` are pinned as class
  constants on `BiolandDocumentPreviewPolicy` from the ticket's spec and have not been verified
  against a live site. Before deploying, run `drush field:info media document` on the target site
  and confirm those are the real machine names; if either differs, update the constants (the
  feature silently no-ops — no document field found — rather than erroring, so this is easy to
  miss).

## Configuration Export/Import

The module's configuration can be exported/imported using Drupal's configuration management system. The configuration is stored in `bioland.settings`.

## Translation Default Feature Details

### How Translation Default Creation Works

**By default, the module is configured to create translation defaults using all available languages** when translatable entities are created or updated.

1. When a translatable entity is created or updated
2. The module checks if translation default creation is enabled (enabled by default)
3. If "Use all available languages" is enabled (default), it gets all installed languages
4. If "Use all available languages" is disabled, it uses the configured target languages
5. For each target language:

- Creates a new translation default (without translating the content). If a translation already exists and its source is proper (not 'und'), it will not be overwritten.
- Sets the translation default as published by default

### Configuration Options

Access the configuration at: `/admin/config/bioland/settings`

#### Translation Default Configuration Settings

- **Automatically create translation defaults**: Enable/disable automatic translation default creation
- **Use all available languages**: When enabled, creates translation defaults for all languages installed on the site
- **Target languages**: Select specific languages to create translation defaults for (only used when "Use all available languages" is disabled)
- **Copy source field values**: Whether to copy translatable field values from source to translation defaults
- **Entity types**: Select which entity types should have automatic translation defaults

### API Usage

You can also use the translation manager service programmatically:

```php
// Get the translation manager service
$translation_manager = \Drupal::service('bioland.translation_manager');

// Create translation defaults for an entity
$translation_manager->createTranslations($entity, 'insert');

// Check if auto-translation default creation is enabled
if ($translation_manager->isAutoTranslationEnabled()) {
  // Do something
}

// Get configured target languages
$languages = $translation_manager->getTargetLanguages();
```

### Helper Functions

The module provides helper functions:

```php
// Check if entity is translatable
if (bioland_entity_is_translatable($entity)) {
  // Process entity
}

// Get translatable field values from entity
$values = bioland_get_translatable_field_values($entity);

// Check if entity has SCBD fields (legacy)
if (bioland_entity_has_scbd_fields($entity)) {
  // Process entity
}

// Get SCBD field values from entity (legacy)
$values = bioland_get_scbd_field_values($entity);
```

### Batch Operations

The translation settings form includes batch operations to process existing entities:

1. Select an entity type from the "Entity type to process" dropdown
2. Click "Create translation defaults for existing entities"
3. The system will process entities in batches of 20
4. Results will show the number of entities processed and translation defaults created

### Logging

Translation default creation activities are logged to the 'bioland' log channel. Check the logs for:

- Successful translation default creation
- Errors during translation default creation
- Configuration issues

### Requirements for Translation Default Feature

- Drupal 10/11
- Content Translation module enabled
- Multiple languages configured on the site
- Translatable entity types configured on the site

## URL screenshots

BL-1191: "Related websites" nodes (content type field `field_type_placement`
tid 13) get an automatic screenshot of the linked site's first screen
(1280x800, WebP) attached as a media image, via ConvertAPI.

- **Requires** the `convertapi/convertapi-php` library
  (`composer require convertapi/convertapi-php --with-all-dependencies`) and
  the `CONVERT_API_SECRET` environment variable (or
  `$settings['bioland_convert_api_secret']` in `settings.php`). The secret is
  never stored in config, exported, or logged; a missing value shows as a
  requirements-page warning, and a missing library as a requirements-page
  error.
- **Toggle**: System Functions tab, "Automatically screenshot 'Related
  websites' links" (`bioland.settings:enable_url_screenshot`, default
  enabled).
- **Trigger**: saving a qualifying node with a URL (on insert), or changing
  the URL on an existing one (on update). The fetch runs from
  `BiolandUrlScreenshotService::destruct()`, called by Drupal's
  `needs_destruction` kernel subscriber right after the editor's response is
  flushed - never inside the save request itself. Under the built-in PHP
  server or drush the response is not fastcgi-flushed, so the call is
  effectively synchronous there.
- **Retry path**: the `bioland_url_screenshot` queue (drained by cron, or
  `drush queue:run bioland_url_screenshot`) is the fallback for an
  in-request attempt that failed transiently (ConvertAPI 429/5xx) or never
  ran (CLI, a fatal error mid-request). `BiolandUrlScreenshotWorker` retries
  a transient failure up to 3 attempts before giving up; a permanent failure
  (any other 4xx) is logged and dropped immediately.
- **ConvertAPI endpoints used**: [HTML to JPG](https://www.convertapi.com/html-to-jpg)
  (`ImageWidth`/`ImageHeight` render exactly the requested viewport - there
  is no separate "viewport" parameter and no full-page mode) chained into
  [JPG to WebP](https://www.convertapi.com/jpg-to-webp) by the stored file
  URL, so the JPG is never re-uploaded.
- The screenshot is attached to the node's existing Attachments field
  (`field_attachments`) at delta 0, so it becomes the record's main image;
  older items are kept (deleting superseded screenshots is out of scope).
  No extra field is created; `bioland_update_9090()` removes the
  `field_website_image` field an earlier revision of this feature added.

## Troubleshooting

### Additional Fields Not Showing

- Ensure Vue.js and ScbdDrupalScbdFieldJs are loaded
- Check browser console for JavaScript errors
- Verify content type is in the supported list (3, 5, 8, 9, 12)

### Auto Summary Not Working

- Ensure both body and summary fields exist on the form
- Check that auto summary is enabled in settings
- Verify jQuery is loaded

### Field Visibility Not Working

- Check that field visibility is enabled in settings
- Ensure content type field (`#edit-field-type-placement`) exists
- Verify target fields exist in the form

### Translation Defaults Not Creating

- Ensure translation default creation is enabled in Bioland settings
- Check that Content Translation module is enabled
- Verify the entity type is selected in Translation Default Configuration Settings
- Confirm the entity is translatable
- Check logs at `/admin/reports/dblog` for translation default creation errors

## Development

To extend the module:

1. **Add Content Types**: Update `contentTypeAdditionalFields` mapping
2. **Add Field Rules**: Modify field visibility functions
3. **Custom Behaviors**: Extend the main Drupal behavior
4. **New Functionality**: Create additional JavaScript files and library definitions

### JavaScript Development Notes

**CRITICAL - NEVER USE `.once()` OR `once()`**

jQuery does NOT have a `.once()` method and Drupal's `once()` function causes errors in this module. Always use data attributes to prevent duplicate event binding.

**Correct pattern:**

```javascript
const element = document.querySelector('#selector');
if (element && !element.dataset.featureInit) {
  element.dataset.featureInit = 'true';
  $(element).on('change.namespace', handler);
}
```

**Track value changes before processing:**

```javascript
// Store in behavior object
lastContentTypeValue: null,

// In handler
if (this.lastContentTypeValue === updatedValue) {
  return; // Value unchanged, skip processing
}
this.lastContentTypeValue = updatedValue;
```

**WRONG - These will cause errors:**

```javascript
$('#selector').once('namespace').each(function() { ... }); // NO .once() method
const elements = once('namespace', '#selector'); // Causes errors
```

This module uses `dataset` attributes and value tracking throughout all JavaScript files to ensure event handlers are only attached once and changes are only processed when values actually change.

