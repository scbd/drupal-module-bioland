# Bioland Module - Install Operations

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
- `_bioland_configure_search_api_index()` - Configure content index with language field and processors
- `_bioland_configure_facets()` - Configure content_type and language facets, remove others

## Form Display Configuration
- `_bioland_configure_langcode_form_display()` - Add langcode field to sidebar with label "Language", collapsed by default

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
