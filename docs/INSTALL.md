# Bioland Module - Install Operations

## Requirements
- `menu_link_attributes` ≥ 1.7 — provides menu link class storage and UI.

Operations performed by `bioland_install()` in order:

## Role Validation & Creation
- Validate required system roles exist: `anonymous`, `authenticated`, `administrator`
- Create custom roles if missing: `scbd_staff`, `site_manager`, `content_manager`, `contributor`, `system`

## Content Type Setup
- Create `content` node type if it doesn't exist

## Permission Assignment
- Grant bioland permissions to `administrator`, `site_manager`, `content_manager`, `scbd_staff`

## Configuration Initialization
- Set default countries from `$settings['scbd_field']['countries']` or fallback to `['lk']`
- `_bioland_update_countries_from_dmsm()` - Fetch countries (and region/continent when present)
  from the DMSM API for the current hostname; non-blocking, falls back silently if DMSM is
  unavailable

## Translation Import
- `_bioland_import_translations()` - Import PO files from `/translations` directory

## Form Display Configuration
- Add `field_order` to `node.content.default` form display

## Search API Integration
- `_bioland_add_field_order_to_search_index()` - Add `field_order` to Search API "content" index

## Field Configuration
- `_bioland_clear_field_help_text()` - Clear help text from `body` and `field_attachments`
- `_bioland_set_full_html_only_format()` - Restrict `body`, `field_description`, `comment_body` to `full_html` only
- `_bioland_update_content_to_full_html()` - Update existing node/media/comment content to use `full_html`
- `_bioland_configure_linkit_profile()` - Configure default Linkit profile
- `_bioland_configure_content_fields()` - Set fields non-translatable, remove `field_published` default, configure Linkit widget on `field_url`
- `_bioland_configure_full_html_format()` - Configure full_html with Linkit, disable basic_html

## Role Permission Sync
- `_bioland_sync_scbd_staff_permissions()` - Copy all permissions from `site_manager` to `scbd_staff`

## Legacy User Cleanup
- `_bioland_block_cbd_int_users()` - Block all @cbd.int users (except `randy.houlahan@cbd.int`), set to system role

## Search API & Facets Configuration
- `_bioland_v2_update_search_and_facets_config()` - Apply the v2 (production) Search API index and
  facets configuration; the canonical path a fresh install runs (see `docs/architecture.md` for the
  v1/v2 distinction)

## Form Display Configuration
- `_bioland_configure_langcode_form_display()` - Add langcode field to sidebar with label "Language", collapsed by default
- `_bioland_disable_comment_field_display()` - Disable the comment field on view displays

## Content Type & Menu Configuration
- `_bioland_configure_content_type_available_menus()` - Configure which menus the content type may link into
- `_bioland_configure_content_types()` - Configure content type taxonomy terms
- `_bioland_configure_content_type_status_by_site_type()` - Enable/disable taxonomy terms by Bioland vs Biosafety Land
- `_bioland_configure_system_pages_search_terms()` - Create the BCH/ABSCH search taxonomy terms
- `_bioland_enable_main_menu_lock()` - Restrict main menu editing to administrators
- `_bioland_create_content_menu_link()` - Create the "Content" admin menu link
- `_bioland_disable_translation_menus()` - Hide menu-parent controls on translation forms
- `_bioland_configure_content_type_weights()` - Set content type ordering weights

## Views Installation
- `_bioland_install_user_admin_view()` - Install the custom user admin view
- `_bioland_install_media_library_view()` - Install the custom media library view with sortable columns

## Menu Item Fixups
- `_bioland_update_menu_item_139()` - Update menu item 139's URL to `/admin/content/media`

## Editor Configuration
- `_bioland_configure_full_html_editor_toolbar()` - Configure the `full_html` CKEditor toolbar

## JSON API Resource Disabling
- `_bioland_disable_jsonapi_resources()` - Disable unused/sensitive JSON API endpoints via `jsonapi_extras` module:
  - `facets_facet_source--facets_facet_source` - Facets module config entities
  - `facets_facet--facets_facet` - Individual facet configurations
  - `jsonapi--jsonapi` - Core JSON:API metadata endpoint
  - `jsonapi--jsonapi_index` - JSON:API resource listing
  - `language_content_settings--language_content_settings` - Translation settings per bundle
  - `linkit_profile--linkit_profile` - Linkit autocomplete profiles
  - `view--view` - Views configurations
  - `search_api_index--search_api_index` - Search API index definitions
  - `search_api_task--search_api_task` - Search API pending tasks
  - `search_api_server--search_api_server` - Search API server connections
  - `mailer_policy--mailer_policy` - Symfony Mailer policies
  - `mailer_transport--mailer_transport` - Symfony Mailer transports
  - `user_role--user_role` - User role definitions
  - `menu--menu` - Menu definitions
  - `user--user` - User accounts (security-sensitive)

## User Provisioning
- `_bioland_provision_users()` - Create/update test, training, staff, and admin users from environment variables
