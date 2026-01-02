# Bioland Module - Update Hooks

Update hooks run in numerical order during `drush updb`:

## 9001
- Create `scbd_staff` role if missing

## 9002
- Create `site_manager` and `content_manager` roles if missing

## 9003
- Create `contributor` role if missing

## 9004
- `_bioland_add_field_order_to_search_index()` - Add `field_order` to Search API index

## 9005
- Grant bioland permissions to `administrator`, `site_manager`, `content_manager`, `scbd_staff`

## 9006
- `_bioland_clear_field_help_text()` - Clear help text from `body` and `field_attachments`

## 9007
- `_bioland_import_translations()` - Import PO translation files

## 9008
- `_bioland_import_translations()` - Re-import translations (updated files)

## 9009
- Import new `ro` (Romanian) translation

## 9010
- Import new `tr` (Turkish) translation

## 9011
- Import batch of 26 additional language translations

## 9012
- Batch update: Set existing nodes to `full_html` format

## 9013
- `_bioland_configure_linkit_profile()` - Configure default Linkit profile with entity suggestions

## 9014
- `_bioland_import_translations()` - Re-import all translations

## 9015
- Create `system` role if missing

## 9016
- `_bioland_set_full_html_only_format()` - Restrict text fields to `full_html` format only

## 9017
- Batch update: Convert all node/media/comment text fields to `full_html` format

## 9018 *(v1.0.30)*
- `_bioland_configure_linkit_profile()` - Configure default Linkit profile with entity suggestions

## 9019 *(v1.0.30)*
- `_bioland_configure_content_fields()` - Configure content type fields:
  - Set non-translatable: `field_attachments`, `field_components`, `field_type_placement`, `field_tags`
  - Remove default value from `field_published`
  - Configure `field_url` with Linkit widget (auto-populate link text enabled)

## 9020 *(v1.0.30)*
- Ensure custom roles exist and grant bioland permissions:
  - Create roles if missing: `scbd_staff`, `site_manager`, `content_manager`, `contributor`, `system`
  - Grant permissions to roles:
    - `administrator`: all bioland permissions
    - `site_manager`: all bioland permissions
    - `content_manager`: all bioland permissions
    - `scbd_staff`: all bioland permissions

## 9021 *(v1.0.30)*
- `_bioland_provision_users()` - Create/update users from environment variables:

**E2E Test Users** (env var: `E2E_USERS` for password, create or update):
| Email | Roles |
|-------|-------|
| e2e-authenticated@chm-cbd.net | system |
| e2e-contributor@chm-cbd.net | system, contributor |
| e2e-content-manager@chm-cbd.net | system, content_manager |
| e2e-site-manager@chm-cbd.net | system, site_manager |
| e2e-scbd-staff@chm-cbd.net | system, scbd_staff |

**Bioland Users** (env var: `BIOLAND_USERS` for password, create or update):
| Email | Roles |
|-------|-------|
| bioland-contributor@chm-cbd.net | system, contributor |
| bioland-cm@chm-cbd.net | system, content_manager |
| bioland-sm@chm-cbd.net | system, site_manager |
| bioland-scbd@chm-cbd.net | system, scbd_staff |
| bioland@chm-cbd.net | system |

**Training Users** (env var: `TRAINING_USERS` for password, create or update):
| Email | Roles |
|-------|-------|
| training-contributor@chm-cbd.net | system, contributor |
| training-cm@chm-cbd.net | system, content_manager |
| training-sm@chm-cbd.net | system, site_manager |
| training@chm-cbd.net | system |

**Legacy Users to Block** (if exists, set to system role only and block):
- anastasia_yb@hotmail.com
- randy@houlahan.ca
- support@chm-cbd.net
- arafalov+cm@gmail.com
- alexandre.rafalovitch@cbd.int
- it@cbd.int
- ray.goh@cbd.int

**SCBD Staff Users** (create or update with `scbd_staff` role):
| Email | Password Env Var |
|-------|------------------|
| alexandre.rafalovitch@un.org | AR |
| frederic.vogel@un.org | FV |
| djessy.monnier@un.org | DM |
| abhinav.prakash@un.org | AP |

**Administrator Users** (create or update with `administrator` role):
| Email | Password Env Var |
|-------|------------------|
| ray.goh@un.org | RG |
| randy.houlahan@un.org | RH |
| rodrigo.elias@un.org | RE |
| blaise.fonseca@un.org | BF |
| vince.gopez@un.org | VG |
| stephane.bilodeau@un.org | SB |
| api-user@chm-cbd.net | API_USER |

**Auto-Role Assignment**:
- All users with `@un.org` email addresses automatically receive `scbd_staff` role
- Applied during user provisioning and via update hook 9023

## 9022 *(v1.0.30)*
- `_bioland_configure_full_html_format()` - Configure full_html text format:
  - Add Linkit extension to `editor.editor.full_html` with `linkit_profile: default`
  - Set image upload max size to 250kb
  - Add Linkit filter to `filter.format.full_html` with title support
  - Add `linkit` and `media` to format dependencies
  - Grant `scbd_staff` access to full_html format
  - Disable `editor.editor.basic_html`

## 9023 *(v1.0.30)*
- `_bioland_sync_scbd_staff_permissions()` - Sync scbd_staff with site_manager:
  - Copy all permissions from `site_manager` role to `scbd_staff` role
  - Ensures scbd_staff has equivalent access to site_manager

## 9024 *(v1.0.30)*
- Block legacy @cbd.int users and configure Search API:

**Block @cbd.int Users:**
- `_bioland_block_cbd_int_users()` - Find all users with @cbd.int email
  - Exclude: `randy.houlahan@cbd.int`
  - Set to `system` role only and block

**Configure Search API Index:**
- `_bioland_configure_search_api_index()` - Configure `search_api.index.content`:
  - Add `language` field (with fallback)
  - Configure field settings: body, changed, dates, tags, title, etc.
  - Set boost: title=21, body=13
  - Configure processors: html_filter, ignorecase, stemmer, stopwords, tokenizer, transliteration
  - Index languages: de, en, fr, nl
  - Only index `content` bundle
  - Index all installed languages on the site

**Configure Facets:**
- `_bioland_configure_facets()` - Configure facet source and facets:
  - Configure `facets.facet_source.jsonapi_search_api_facets__content`
  - Configure `facets.facet.content_type` (field: tid)
  - Configure `facets.facet.language` (field: language)
  - Remove all other facets

## 9025 *(v1.0.30)*
- `_bioland_configure_langcode_form_display()` - Add langcode field to content form:
  - Add `langcode` field with label "Language" to content node form
  - Enable `language_alterable` for content node type
  - Use `language_select` widget
  - Weight 11 (after field_order)

## 9026 *(v1.0.30)*
- Enable language alterable for content node type:
  - Re-run of `_bioland_configure_langcode_form_display()` for updates

## 9027 *(v1.0.30)*
- `_bioland_configure_content_type_available_menus()` - Configure available menus for content type

## 9028 *(v1.0.30)*
- `_bioland_configure_content_types()` - Configure content type taxonomy terms

## 9029 *(v1.0.31)*
- Ensure all bioland users have the system role:
  - Updates existing bioland users to add missing system role
  - Checks and adds roles to:
    - `bioland-contributor@chm-cbd.net` → system, contributor
    - `bioland-cm@chm-cbd.net` → system, content_manager
    - `bioland-sm@chm-cbd.net` → system, site_manager
    - `bioland-scbd@chm-cbd.net` → system, scbd_staff
    - `bioland@chm-cbd.net` → system

## 9030 *(v1.0.31)*
- Grant `scbd_staff` role to all users with `@un.org` email addresses:
  - Automatically scans all active users for `@un.org` emails
  - Adds `scbd_staff` role if not already present
  - Applies to both existing and future users (via `_bioland_provision_users()`)
  - Case-insensitive email matching

## 9031 *(v1.0.31)*
- Restrict maintenance mode access to `administrator` and `scbd_staff` roles only:
  - **Applied on module install** via `_bioland_configure_maintenance_mode_access()`
  - Grants `access site in maintenance mode` permission to:
    - `administrator` role
    - `scbd_staff` role
  - Revokes permission from all other roles:
    - `site_manager`
    - `content_manager`
    - `contributor`
    - `system`
    - `authenticated`

## 9032 *(v1.0.31)*
- Grant admin/config access permissions to `scbd_staff`, `site_manager`, and `content_manager` roles:
  - **Applied on module install** via `_bioland_get_standard_permission_matrix()`
  - Grants permissions:
    - `access administration pages` - Access to /admin URLs
    - `view the administration theme` - See admin theme instead of front-end theme
    - `access site reports` - View status reports and logs
  - Applies to roles:
    - `scbd_staff`
    - `site_manager`
    - `content_manager`

## 9033 *(v1.0.31)*
- Update all text format fields to use `full_html`:
  - **Applied on module install** via `_bioland_update_content_to_full_html()`
  - Updates existing records across entity types:
    - **Nodes**: Updates `body` field format
    - **Media**: Updates `field_description` field format
    - **Comments**: Updates `comment_body` field format
  - Changes any format (basic_html, plain_text, etc.) to `full_html`
  - Prevents revision creation during format update
  - Temporarily disables translation auto-creation to avoid errors
  - Logs updated count and any errors to 'bioland' log channel

## 9034 *(v1.0.31)*
- Add system_pages search taxonomy terms:
  - **Applied on module install** via `_bioland_configure_system_pages_search_terms()`
  - Creates terms with specific IDs:
    - **tid 52**: "Biosafety Clearing-House Search" (parent: tid 23)
    - **tid 53**: "Access And Benefit-sharing Clearing-house Search" (parent: tid 52)
  - Maintains parent-child hierarchy:
    - BCH Search (52) is child of parent term (23)
    - ABSCH Search (53) is child of BCH Search (52)
  - Terms are created in `system_pages` vocabulary
  - Status: Published (enabled)

## 9035 *(v1.0.31)*
- `_bioland_configure_langcode_form_display()` - Add langcode field to content form:
  - Add `langcode` field with label "Language" to content node form
  - Place in sidebar using `details_sidebar` format (if field_group module available)
  - Collapsed by default
  - Weight 99 (near end of sidebar, after field_order)

## 9036 *(v1.0.31)*
- Disable unused JSON API resource endpoints for security:
  - **Requires**: `jsonapi_extras` module (added to module dependencies)
  - **Applied on module install** via `_bioland_disable_jsonapi_resources()`
  - Creates `jsonapi_extras.jsonapi_resource_config.*` config with `disabled: TRUE`
  - **Disabled endpoints** (15 total):

| Resource Type | Entity | Description |
|---------------|--------|-------------|
| `facets_facet_source--facets_facet_source` | Facet Source | Facets module config entities |
| `facets_facet--facets_facet` | Facet | Individual facet configurations |
| `jsonapi--jsonapi` | JSON:API | Core JSON:API metadata endpoint |
| `jsonapi--jsonapi_index` | JSON:API Index | JSON:API resource listing |
| `language_content_settings--language_content_settings` | Language Settings | Content translation settings per bundle |
| `linkit_profile--linkit_profile` | Linkit Profile | Linkit autocomplete profiles |
| `view--view` | View | Views configurations |
| `search_api_index--search_api_index` | Search Index | Search API index definitions |
| `search_api_task--search_api_task` | Search Task | Search API pending tasks |
| `search_api_server--search_api_server` | Search Server | Search API server connections |
| `mailer_policy--mailer_policy` | Mailer Policy | Symfony Mailer policies |
| `mailer_transport--mailer_transport` | Mailer Transport | Symfony Mailer transports |
| `user_role--user_role` | User Role | User role definitions |
| `menu--menu` | Menu | Menu definitions |
| `user--user` | User | **User accounts** (security-sensitive) |

  - **Security rationale**: These endpoints expose configuration and user data that should not be publicly accessible via JSON:API