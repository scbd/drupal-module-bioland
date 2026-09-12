# AI Agent Instructions: Drupal Module Bioland

## Important!
- if this exists follow these memory rules every questions `.github/instructions/personal.md`
- if it exists the default guidance at `.github/instructions/default.instructions.md` is the canonical source of instructions.
- **CRITICAL**: Follow the Jira and Git workflow at `.github/instructions/workflow.md` for ALL code generation tasks.

## Project Overview
This is a Drupal 9/10/11 custom module (`bioland`) that provides comprehensive field management and translation functionality. The module combines:
- **Backend**: Drupal PHP services architecture (Settings, Field Functionality, Translation Management)
- **Frontend**: jQuery-based behaviors with Vue.js integration for dynamic fields
- **Features**: Field visibility control, additional fields, auto summary generation, and translation defaults

## Architecture & Key Components

### Service Architecture (Drupal Standard)
- **BiolandSettingsManager**: `src/Service/BiolandSettingsManager.php` - General config access layer
- **BiolandFieldFunctionalityManager**: `src/Service/BiolandFieldFunctionalityManager.php` - Manages feature toggles and JS settings
- **BiolandTranslationManager**: `src/Service/BiolandTranslationManager.php` - Handles automatic translation default creation
- **BiolandTranslationBatchService**: `src/Service/BiolandTranslationBatchService.php` - Batch processing for existing entities
- **Config Form**: `src/Form/BiolandSettingsForm.php` at `/admin/config/bioland/settings`

### Frontend Integration Pattern
The module uses a **modular JavaScript behavior approach**:
1. Three separate library definitions in `bioland.libraries.yml` (field_visibility, additional_fields, auto_summary)
2. Each feature has its own JavaScript file in `js/` directory
3. Features are conditionally attached via `bioland_form_alter()` based on config settings
4. Settings passed to JS via `drupalSettings.bioland` object
5. Vue.js integration for additional fields mounts into dynamically created `#bl-additional-fields` element

**Critical**: Vue component (`ScbdDrupalScbdFieldJs.default`) loaded from external CDN—source NOT in this repo.

### Feature Toggle System
All frontend features can be individually enabled/disabled:
```php
// In BiolandFieldFunctionalityManager::getJavaScriptSettings()
return [
  'enableFieldVisibility' => $c->get('enable_field_visibility') !== FALSE,
  'enableAdditionalFields' => $c->get('enable_additional_fields') !== FALSE,
  'enableAutoSummary' => $c->get('enable_auto_summary') !== FALSE,
  'fieldVisibilityRules' => $c->get('field_visibility_rules') ?: '',
];
```

## Development Workflows

### Testing (Dual Stack)
```bash
# PHP tests (PHPUnit 9.6)
composer test                    # Run tests
vendor/bin/phpunit              # Direct PHPUnit

# JavaScript tests (Jest)
npm test                         # Jest in CI mode
npx jest --ci --coverage         # With coverage

# Full local run (lint + PHP + JS)
npm run test:all

# CI (.github/workflows/ci.yml) runs lint, PHP, and JS as separate steps
```

### Installation & Dependency Management
**Critical dependencies** (enforced in `bioland.info.yml` and `bioland_requirements()`):
- `auto_node_translate`
- `auto_node_translate_deepl`
- `auto_node_translate_amazon`
- `ant_bulk`
- `fontawesome`

Install via Composer before enabling:
```bash
composer require drupal/auto_node_translate --with-all-dependencies
# ... repeat for other dependencies
```

### Git Workflow
- Use conventional commit prefixes: `feat:`, `fix:`, `chore:`, `docs:`, `test:`
- Module version: `0.0.1` (see `package.json`, `composer.json`)

## Code Patterns & Conventions

### PHP Coding Standards
- **PSR-12** (NOT Drupal coding standards—inherited from scbd_field architecture)
- Namespace: `Drupal\bioland\{Service|Form}\...`
- Service injection via `bioland.services.yml`
- Config access: `\Drupal::config('bioland.settings')->get('key')`

### JavaScript Patterns (Drupal Behaviors)
```javascript
// Standard pattern used in all three JS files
Drupal.behaviors.biolandFeatureName = {
  attach: function (context, settings) {
    const biolandSettings = settings.bioland || {};
    
    // Check if feature is enabled
    if (biolandSettings.enableFeatureName === false) {
      return;
    }
    
    // Initialize feature
    this.initializeFeature(context, biolandSettings);
  }
};
```

**Key patterns**:
- **NEVER use `.once()` or `once()`** - jQuery doesn't have a `.once()` method, and Drupal's `once()` function causes errors
  - **CRITICAL**: Do NOT use any form of `.once()` or `once()` - they don't work and will break the code
- **Prevent duplicate event binding**: Use `element.dataset.featureInit` flag to check if already initialized
  - Pattern: `if (element.dataset.biolandFeatureInit) return; element.dataset.biolandFeatureInit = 'true';`
  - This prevents attaching multiple event listeners when Drupal behaviors re-run
- **Track value changes**: Store `lastContentTypeValue` to detect actual changes before processing
  - Pattern: `if (this.lastContentTypeValue === updatedValue) return;`
  - Only process when value actually changes
- Direct DOM manipulation for performance: `document.querySelector()`, `element.style.display`
- Namespace event listeners: `.on('change.biolandAutoSummary')` to prevent conflicts

### Configuration Schema
`config/schema/bioland.schema.yml` defines:
- `bioland.settings`: Complete config structure with nested translation settings
- All boolean flags default to `true` in `config/install/bioland.settings.yml`
- **Always update schema when adding config keys** (Drupal requirement)

### Translation Files
No PO files in this module—uses standard Drupal translation (`t()` function).

## Common Tasks for AI Agents

### Adding a New Config Option
1. Update `BiolandSettingsForm::buildForm()` to add form element
2. Update `submitForm()` to save the value (handle array normalization for textareas)
3. Add schema entry in `config/schema/bioland.schema.yml`
4. If frontend-facing: update `BiolandFieldFunctionalityManager::getJavaScriptSettings()`
5. Update corresponding JS behavior to read from `settings.bioland.{yourKey}`

### Adding a New Feature (e.g., "Smart Categorization")
1. Create `js/bioland.smart-categorization.js` with behavior pattern
2. Add library definition in `bioland.libraries.yml`:
   ```yaml
   smart_categorization:
     js:
       js/bioland.smart-categorization.js: {}
     dependencies:
       - core/jquery
       - core/drupal
   ```
3. Add checkbox in settings form: `enable_smart_categorization`
4. Update `BiolandFieldFunctionalityManager::isAnyFunctionalityEnabled()` and `getJavaScriptSettings()`
5. Update `bioland_form_alter()` to conditionally attach library
6. Add schema entry and default value

### Modifying Translation Default Logic
1. Edit `BiolandTranslationManager::createTranslations()` for core behavior
2. Update `prepareTranslationValues()` for field copying logic
3. Batch operations: modify `BiolandTranslationBatchService::processTranslationBatch()`
4. Test with: Enable module, create node, check translation tabs

### Debugging Form Alterations
- Check `bioland_form_alter()` only targets `node_content_form`
- Verify `drupalSettings.bioland` object in browser console
- Use `console.log('Bioland: ...')` pattern (already in all JS files)
- Check that content type machine name is `content` (enforced in `bioland.install`)

## CI/CD & Deployment

### CircleCI Configuration
Two executors (`node_executor`, `php_executor`) run parallel jobs:
- **js-tests**: Node 20.11, runs Jest with coverage
- **php-tests**: PHP 8.2, runs PHPUnit with JUnit output
- Caches: `~/.npm`, `~/.composer/cache`, `vendor`

### Deployment Commands
**Note**: `package.json` contains `deploy:dev` script for rsync to staging server. Update path before use.

## Critical Files Reference
- **Entry points**: `bioland.info.yml` (module definition), `bioland.module` (hooks), `bioland.libraries.yml` (asset loading)
- **Core services**: All files in `src/Service`
- **Form logic**: `src/Form/BiolandSettingsForm.php`
- **Install hooks**: `bioland.install` (role creation, content type validation, update hooks)
- **Frontend**: `js/bioland.{field-visibility|additional-fields|auto-summary}.js`
- **Config**: `config/install/bioland.settings.yml`, `config/schema/bioland.schema.yml`
- **Tests**: `tests/Unit/SmokeTest.php`, `js/hello.test.js` (minimal fixtures)

## Anti-Patterns to Avoid
- ❌ Don't modify external Vue component (ScbdDrupalScbdFieldJs) behavior—it's external
- ❌ Don't assume `node_content_form` exists—installer creates it, but validate in runtime code
- ❌ Don't skip dependency checks—all 5 contrib modules must be enabled (see `bioland_requirements()`)
- ❌ Don't hardcode language codes—use `$this->languageManager->getLanguages()`
- ❌ Don't add features without updating `BiolandFieldFunctionalityManager`—breaks conditional loading
- ❌ Don't use Drupal coding standards—this project inherits PSR-12 from scbd_field architecture
- ❌ Don't create translation if existing translation has proper source (not 'und')—see `BiolandTranslationManager::createTranslations()` logic
- ❌ **NEVER use `.once()` or `once()` functions**—jQuery doesn't have `.once()`, causes errors
  - Instead use data attributes to track initialization: `if (element.dataset.featureInit) return; element.dataset.featureInit = 'true';`

## Testing Strategy
- **PHP**: Smoke test in `tests/Unit/SmokeTest.php` (extends PHPUnit\Framework\TestCase)
- **JS**: Smoke test in `js/hello.test.js` (Jest configuration in `jest.config.js`)
- **Coverage**: Jest collects coverage from `js/**/*.js`, PHPUnit from `src`
- **Local validation**: Run `composer test && npm test` before commits

## Translation Default Feature Architecture
**Default behavior**: Auto-create enabled, use all languages, don't copy source values.

Key methods:
- `createTranslations()`: Main entry point (called from entity insert/update hooks)
- `prepareTranslationValues()`: Handles field copying logic
- `shouldMountAdditionalFields()`: Content type → field type mapping (3→events, 5→projects, etc.)

**Batch processing**:
- UI in settings form → `submitBatchForm()`
- Chunks of 20 entities processed via `BiolandTranslationBatchService`
- Results logged to 'bioland' channel

## Content Type Requirements
- **Primary form**: `node_content_form` (content type machine name: `content`)
- **Additional fields mapping** (in `js/bioland-additional-fields-1-0-21.js`):
  - Type 3: eventStatuses
  - Type 5: projectStatuses, geoScopes
  - Type 8: orgTypes, govTypes
  - Type 9: ecosystemTypes
  - Type 12: documentTypes
