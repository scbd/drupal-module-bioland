# Refactoring Plan: bioland.install → Modular Include Files

## Overview

Split the 5,144-line monolithic `bioland.install` into ~15 domain-specific include files under `includes/`. The main file retains only hook implementations with `require_once` statements.

**Total Estimated Effort**: 17 checkpoints across 4 phases

---

## Phase 1: Foundation (CP01–CP03)

### CP01_HELPERS — Core Utilities
**Target**: `includes/bioland.install.helpers.inc` (~50 lines)

**Functions to extract**:
- `_bioland_require_module()` (lines 22-38)

**Shared `use` statements to include**:
```php
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
```

**Validation**: Grep for `_bioland_require_module` calls — all other files will depend on this.

---

### CP02_ROLES — Role & Permission Management
**Target**: `includes/bioland.install.roles.inc` (~500 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_ensure_roles_exist()` | 40-63 | None |
| `_bioland_grant_role_permissions()` | 65-90 | None |
| `_bioland_get_standard_roles()` | 165-175 | None |
| `_bioland_get_standard_permission_matrix()` | 177-196 | None |
| `_bioland_configure_maintenance_mode_access()` | 198-224 | `_bioland_grant_role_permissions()` |
| `_bioland_sync_scbd_staff_permissions()` | 2299-2330 | Role entity |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9006()` | Ensure SCBD Staff role |
| `bioland_update_9020()` | Ensure roles and permissions |
| `bioland_update_9032()` | Maintenance mode access |
| `bioland_update_9033()` | Grant admin access permissions |
| `bioland_update_9045()` | Sync scbd_staff permissions |

**Required `use` statements**:
```php
use Drupal\user\Entity\Role;
```

**Validation**: Run `composer test` — role/permission functions are isolated.

---

### CP03_USERS — User Management
**Target**: `includes/bioland.install.users.inc` (~600 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_manage_user()` | 92-163 | Role entity |
| `_bioland_provision_users()` | 1880-2032 | Multiple helpers below |
| `_bioland_create_user_if_not_exists()` | 2050-2051 | `_bioland_manage_user()` |
| `_bioland_grant_scbd_staff_to_un_org_users()` | 2063-2099 | Role entity |
| `_bioland_block_legacy_user()` | 2113-2114 | `_bioland_manage_user()` |
| `_bioland_create_or_update_user()` | 2132-2133 | `_bioland_manage_user()` |
| `_bioland_block_cbd_int_users()` | 2416-2468 | Role entity |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9021()` | Provision users |
| `bioland_update_9022()` | Configure full_html (user-related) |
| `bioland_update_9025()` | Block @cbd.int users |
| `bioland_update_9030()` | Ensure bioland users have system role |
| `bioland_update_9031()` | Grant scbd_staff to @un.org users |

**Validation**: Test user provisioning logic in isolation.

---

## Phase 2: Major Domains (CP04–CP08)

### CP04_SEARCH — Search API & Facets (LARGEST)
**Target**: `includes/bioland.install.search.inc` (~1000 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_add_field_order_to_search_index()` | 1201-1246 | `_bioland_require_module()` |
| `_bioland_configure_search_api_index()` | 2477-2817 | `_bioland_require_module()` |
| `_bioland_ensure_search_api_tables_exist()` | 2833-2884 | None |
| `_bioland_clear_pending_search_api_tasks()` | 2897-2936 | `_bioland_require_module()` |
| `_bioland_execute_pending_search_api_tasks()` | 2948-3016 | `_bioland_require_module()` |
| `_bioland_disable_search_index()` | 3029-3055 | `_bioland_require_module()` |
| `_bioland_final_reindex()` | 3068-3125 | Multiple helpers |
| `_bioland_rebuild_search_index_tracking()` | 3137-3161 | `_bioland_require_module()` |
| `_bioland_configure_facets()` | 3172-3261 | `_bioland_require_module()`, `_bioland_get_facet_processor_configs()` |
| `_bioland_get_facet_processor_configs()` | 3270-3297 | None |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9011()` | Add field_order to search index |
| `bioland_update_9026()` | Configure facets |

**Validation**: Search API is core functionality — test thoroughly.

---

### CP05_EDITOR — Text Format & Editor
**Target**: `includes/bioland.install.editor.inc` (~550 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_set_full_html_only_format()` | 540-601 | FieldConfig |
| `_bioland_update_content_to_full_html()` | 608-733 | Database |
| `_bioland_configure_full_html_format()` | 2157-2278 | `_bioland_require_module()` |
| `_bioland_configure_full_html_editor_toolbar()` | 4072-4174 | None |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9016()` | Set full_html only format |
| `bioland_update_9017()` | Update content to full_html |
| `bioland_update_9034()` | Update text formats to full_html |
| `bioland_update_9038()` | Configure editor toolbar |

**Required `use` statements**:
```php
use Drupal\field\Entity\FieldConfig;
```

---

### CP06_LINKIT — Linkit Configuration
**Target**: `includes/bioland.install.linkit.inc` (~300 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_get_linkit_matchers()` | 270-325 | None |
| `_bioland_configure_linkit_profile()` | 1562-1610 | `_bioland_require_module()`, `_bioland_get_linkit_matchers()` |
| `_bioland_configure_linkit_editor()` | 1623-1709 | None |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9013()` | Configure URL field with Linkit |
| `bioland_update_9018()` | Configure Linkit profile |
| `bioland_update_9019()` | Configure content fields (Linkit widget) |

---

### CP07_CONTENT_TYPES — Content Type & Taxonomy
**Target**: `includes/bioland.install.content_types.inc` (~600 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_configure_content_type_available_menus()` | 3441-3482 | None |
| `_bioland_configure_content_types()` | 3494-3600 | None |
| `_bioland_configure_content_type_status_by_site_type()` | 3615-3718 | None |
| `_bioland_configure_system_pages_search_terms()` | 3730-3836 | None |
| `_bioland_configure_content_type_weights()` | 4263-4295 | None |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9028()` | Configure available menus |
| `bioland_update_9029()` | Configure content types |
| `bioland_update_9035()` | Add system_pages search terms |
| `bioland_update_9039()` | Configure content type status |
| `bioland_update_9041()` | Configure content type weights |

---

### CP08_FIELDS — Field Configuration
**Target**: `includes/bioland.install.fields.inc` (~450 lines)

**Helper functions to extract**:
| Function | Lines | Dependencies |
|----------|-------|--------------|
| `_bioland_clear_field_help_text()` | 847-860 | FieldConfig |
| `_bioland_configure_content_fields()` | 1737-1800 | FieldConfig, FormDisplay |
| `_bioland_install_optional_field_configs()` | 4697-4786 | FieldStorageConfig, FieldConfig |

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9004()` | Add field_order field |
| `bioland_update_9005()` | Clear help text |
| `bioland_update_9007()` | Set max limit on field_order |
| `bioland_update_9014()` | Add promotion options help |
| `bioland_update_9024()` | Update field_url label |
| `bioland_update_9047()` | Install optional configs |

**Required `use` statements**:
```php
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
```

---

## Phase 3: Supporting Domains (CP09–CP14)

### CP09_FORM_DISPLAY
**Target**: `includes/bioland.install.form_display.inc` (~200 lines)

**Helper functions**: `_bioland_configure_langcode_form_display()` (3319-3408)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9027()` | Enable language alterable |
| `bioland_update_9036()` | Add langcode to form |
| `bioland_update_9053()` | Hide menu parent on translation forms |

---

### CP10_VIEWS
**Target**: `includes/bioland.install.views.inc` (~550 lines)

**Helper functions**:
- `_bioland_install_user_admin_view()` (4362-4496)
- `_bioland_install_comment_admin_view()` (4508-4607)
- `_bioland_install_media_library_view()` (4619-4685)
- `_bioland_fix_comment_admin_view_filter()` (4891-4988)
- `_bioland_fix_user_admin_email_formatter()` (5006-5043)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9042()` | Install user admin view |
| `bioland_update_9043()` | Install comment admin view |
| `bioland_update_9044()` | Install media library view |
| `bioland_update_9049()` | Fix comment view filter |
| `bioland_update_9050()` | Fix email formatter |

---

### CP11_MENU
**Target**: `includes/bioland.install.menu.inc` (~300 lines)

**Helper functions**:
- `_bioland_enable_main_menu_lock()` (4186-4244)
- `_bioland_create_content_menu_link()` (4804-4869)
- `_bioland_update_menu_item_139()` (5091-5126)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9040()` | Enable main menu lock |
| `bioland_update_9048()` | Create Content menu link |
| `bioland_update_9051()` | Recreate Content menu link |
| `bioland_update_9052()` | Update menu item 139 URL |

---

### CP12_JSONAPI
**Target**: `includes/bioland.install.jsonapi.inc` (~100 lines)

**Helper functions**: `_bioland_disable_jsonapi_resources()` (4004-4059)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9037()` | Disable JSON API resources |

---

### CP13_TRANSLATION
**Target**: `includes/bioland.install.translation.inc` (~150 lines)

**Helper functions**: `_bioland_import_translations()` (739-827)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9002()` | Import translations |
| `bioland_update_9003()` | Set default entity type for translation |
| `bioland_update_9012()` | Create translation defaults (batch) |

---

### CP14_DMSM
**Target**: `includes/bioland.install.dmsm.inc` (~100 lines)

**Helper functions**: `_bioland_update_countries_from_dmsm()` (339-350)

**Update hooks to include**:
| Function | Calls |
|----------|-------|
| `bioland_update_9001()` | Migrate country → countries |
| `bioland_update_9023()` | Update countries from DMSM |
| `bioland_update_9046()` | Update region/continent from DMSM |

---

## Phase 4: Finalization (CP15–CP16)

### CP15_MAIN — Refactor Main Install File
**Target**: `bioland.install` (~300 lines)

Final structure:
```php
<?php
/**
 * @file
 * Install, update and uninstall functions for the Bioland module.
 */

// Load helper includes (each contains related helper functions AND update hooks).
require_once __DIR__ . '/includes/bioland.install.helpers.inc';
require_once __DIR__ . '/includes/bioland.install.roles.inc';
require_once __DIR__ . '/includes/bioland.install.users.inc';
require_once __DIR__ . '/includes/bioland.install.search.inc';
require_once __DIR__ . '/includes/bioland.install.editor.inc';
require_once __DIR__ . '/includes/bioland.install.linkit.inc';
require_once __DIR__ . '/includes/bioland.install.content_types.inc';
require_once __DIR__ . '/includes/bioland.install.fields.inc';
require_once __DIR__ . '/includes/bioland.install.form_display.inc';
require_once __DIR__ . '/includes/bioland.install.views.inc';
require_once __DIR__ . '/includes/bioland.install.menu.inc';
require_once __DIR__ . '/includes/bioland.install.jsonapi.inc';
require_once __DIR__ . '/includes/bioland.install.translation.inc';
require_once __DIR__ . '/includes/bioland.install.dmsm.inc';

/**
 * Implements hook_install().
 */
function bioland_install() {
  // ... (unchanged logic, now calls functions from includes)
}

/**
 * Implements hook_requirements().
 */
function bioland_requirements($phase) {
  // ... (unchanged logic)
}
```

---

### CP16_VALIDATE — Final Validation
1. Run `composer test`
2. Run `npm test`
3. Verify fresh install works
4. Verify update hooks run correctly
5. Clear caches and verify module functions

---

## Dependency Graph

```
bioland.install
├── bioland.install.helpers.inc (no deps)
├── bioland.install.roles.inc
│   └── depends on: helpers
├── bioland.install.users.inc
│   └── depends on: helpers, roles
├── bioland.install.search.inc
│   └── depends on: helpers
├── bioland.install.editor.inc
│   └── depends on: helpers
├── bioland.install.linkit.inc
│   └── depends on: helpers
├── bioland.install.content_types.inc (no deps)
├── bioland.install.fields.inc (no deps)
├── bioland.install.form_display.inc (no deps)
├── bioland.install.views.inc
│   └── depends on: helpers
├── bioland.install.menu.inc (no deps)
├── bioland.install.jsonapi.inc
│   └── depends on: helpers
├── bioland.install.translation.inc (no deps)
├── bioland.install.dmsm.inc (no deps)
```

**Note**: Each domain file contains BOTH helper functions AND their related update hooks, so debugging a feature requires loading only one file.

---

## File Structure After Refactor

```
bioland/
├── bioland.install              # ~300 lines (hooks + requires)
└── includes/
    ├── bioland.install.helpers.inc        # ~50 lines
    ├── bioland.install.roles.inc          # ~500 lines (helpers + updates)
    ├── bioland.install.users.inc          # ~600 lines (helpers + updates)
    ├── bioland.install.search.inc         # ~1000 lines (helpers + updates)
    ├── bioland.install.editor.inc         # ~550 lines (helpers + updates)
    ├── bioland.install.linkit.inc         # ~300 lines (helpers + updates)
    ├── bioland.install.content_types.inc  # ~600 lines (helpers + updates)
    ├── bioland.install.fields.inc         # ~450 lines (helpers + updates)
    ├── bioland.install.form_display.inc   # ~200 lines (helpers + updates)
    ├── bioland.install.views.inc          # ~550 lines (helpers + updates)
    ├── bioland.install.menu.inc           # ~300 lines (helpers + updates)
    ├── bioland.install.jsonapi.inc        # ~100 lines (helpers + updates)
    ├── bioland.install.translation.inc    # ~150 lines (helpers + updates)
    └── bioland.install.dmsm.inc           # ~100 lines (helpers + updates)
```

**Key benefit**: When debugging a specific domain (e.g., Views), an AI agent only needs to load `bioland.install.views.inc` (~550 lines) instead of the full 5,144-line file.

---

## Execution Order

| Order | Checkpoint | Est. Lines | Priority |
|-------|-----------|------------|----------|
| 1 | CP01_HELPERS | 50 | Required first |
| 2 | CP02_ROLES | 500 | Foundation |
| 3 | CP03_USERS | 600 | Foundation |
| 4 | CP04_SEARCH | 1000 | Major (largest) |
| 5 | CP05_EDITOR | 550 | Major |
| 6 | CP06_LINKIT | 300 | Major |
| 7 | CP07_CONTENT_TYPES | 600 | Major |
| 8 | CP08_FIELDS | 450 | Major |
| 9 | CP09_FORM_DISPLAY | 200 | Supporting |
| 10 | CP10_VIEWS | 550 | Supporting |
| 11 | CP11_MENU | 300 | Supporting |
| 12 | CP12_JSONAPI | 100 | Supporting |
| 13 | CP13_TRANSLATION | 150 | Supporting |
| 14 | CP14_DMSM | 100 | Supporting |
| 15 | CP15_MAIN | 300 | Finalization |
| 16 | CP16_VALIDATE | - | Verification |

---

## Risk Mitigation

1. **Function not found errors**: Each checkpoint validates the include loads correctly before proceeding
2. **Circular dependencies**: Helpers loaded first; dependency graph prevents cycles
3. **Update hook discovery**: Drupal scans `.install` file — ensure `require_once` is at top level
4. **Missing use statements**: Each include has its own declarations; validate with `composer test`

---

## Resume Instructions

If interrupted, check `CONTINUE.md` for current checkpoint and resume from there.
