# Bioland

The Bioland module is the site-behaviour layer for Bioland and Biosafety Land Drupal sites in the
SCBD platform. It bundles the content model, editorial conveniences, translation defaults,
geographic configuration, navigation, search wiring, and a settings UI that a clearing-house site
needs on top of Drupal core. This glossary is the ubiquitous language for that one model. Generic
Drupal concepts (entity, node, hook, config object) are only defined here where Bioland gives them
a specific meaning.

## Language

### Site identity and geography

**Bioland site**:
A clearing-house website built on this module where `is_biosafety_land` is false. Branding,
available menus, and enabled content terms differ from a Biosafety Land site.
_Avoid_: BL2 site (BL2 is the multi-site code, not the site type), CHM site.

**Biosafety Land site**:
A clearing-house website built on this module where `is_biosafety_land` is true. The same module,
configured for biosafety content rather than general biodiversity content.
_Avoid_: BSL site (BSL is the multi-site code), biosafety clearing-house.

**Multi-site code**:
The short code (`bl2` or `bsl`) parsed out of a site's hostname that decides whether the site is a
Bioland site or a Biosafety Land site. It sets `is_biosafety_land`.
_Avoid_: site type, deployment code.

**Site code**:
The short per-site identifier parsed from a hostname (for example `be`, `fr`), used together with
the environment and multi-site code to address the DMSM API.
_Avoid_: country code (the site code is not always the country), instance id.

**Country**:
An ISO country code attached to a site that scopes its geographic content and seeds its map widgets.
A site can have several. The authoritative list is fetched from DMSM; the static preset table is
keyed on the same code.
_Avoid_: nation, locale, region.

**Region** / **Continent**:
Coarser geographic descriptors for a site (for example `north_america`), set from defaults and
overwritten by DMSM when present. They sit alongside Country, not instead of it.
_Avoid_: area, zone.

**Country map default**:
A preset zoom level and centre coordinate pair, held for ~240 countries in a static service, used to
seed a country's GBIF map widget when no admin override exists. A saved override always wins.
_Avoid_: map config, default settings.

### Content model and editing

**Content** (content type):
The single primary content type this module owns, machine name `content`, bound to the
`node_content_form`. Nearly all of Bioland's form and field behaviour targets it.
_Avoid_: page, article, the content type "type".

**Content type term**:
A taxonomy term in the `tags` vocabulary (News, Event, Project, Document, and so on) that classifies
a Content node and drives the `/node/add` landing page, faceted search, and field visibility. Each
has a numeric term id used throughout the JavaScript rules.
_Avoid_: content type (that is the Drupal bundle), category, type placement.

**Field visibility rule**:
A JSON-defined condition set, keyed by content-type-term id, that shows or hides a target field on
the content form based on the value of another field. Evaluated client-side.
_Avoid_: conditional field, field state, display rule.

**Additional field**:
A thesaurus-backed extra field (event status, project status, organization type, ecosystem type,
document type) mounted onto the content form for specific content-type terms, rendered by the
sibling scbd_field widget.
_Avoid_: extra field, dynamic field, custom field.

**Auto summary**:
The client behaviour that derives a node's summary from its body text as the editor types, with
sentence-boundary truncation and HTML stripping.
_Avoid_: teaser generation, excerpt.

**Help comment**:
Translatable inline guidance text shown next to specific content-form fields (body, attachments,
promotion, order override), configured in the settings UI.
_Avoid_: help text, tooltip, description.

**Order override**:
The `field_order` integer (1 to 10000, default 10000) on a Content node that overrides default
sort order in lists and is fed into the search index.
_Avoid_: weight, position, sort field.

### Translation

**Translation default**:
A placeholder translation created automatically in a target language when a node is saved, marked
outdated so editors know it still needs real translation. It is never overwritten if a proper
translation already exists.
_Avoid_: translation, stub translation, auto-translation (no machine translation is performed here),
placeholder.

**Target language**:
A language a translation default is created for. Either every enabled language on the site
(`use_all_languages`) or an explicit list.
_Avoid_: locale, destination language.

**Translation batch**:
A queued, chunked run of the translation-default logic over existing nodes, used to backfill sites
that already have content.
_Avoid_: bulk translate, migration.

**Lolspeak**:
The `en-x-lolspeak` pseudo-language used for development. The module hides it from non-administrators
across translation overviews, language selectors, and local tasks.
_Avoid_: test language, debug locale.

### Navigation and configuration

**Mega menu setting**:
Per-site configuration deciding which menus (content types, country profiles, focal points, national
targets, BCH, ABSCH, forums, and so on) appear in the front-end mega menu and in what position. The
front end (the bioland-head Nuxt frontend) reads it.
_Avoid_: navigation config, menu block settings.

**Home widget**:
A toggleable front-end homepage panel (GBIF map, latest news, national targets, e-learning, and
others) whose enable flags and per-country map data this module publishes to the front end.
_Avoid_: block, dashboard widget, component.

**Main menu lock**:
A guard that prevents non-administrators from restructuring the main menu, so navigation is managed
only from the source language.
_Avoid_: menu protection, readonly menu.

**Settings section**:
One tab of the Bioland settings form (general, field visibility, tags, help comments, front end and
its subsections, system functions, admin), selected by the route's `section` parameter.
_Avoid_: settings page, panel, screen.

### External authorities

**DMSM**:
The Dynamic Multi-Site Manager, an external SCBD API addressed as
`config/{env}/{multiSiteCode}/{siteCode}`. It is the authority for a site's countries, region, and
continent. Bioland conforms to its response shape; it does not own DMSM's model.
_Avoid_: site manager, config API, provisioning service.

**GBIF**:
The Global Biodiversity Information Facility, the external biodiversity occurrence service the home
map widget renders against. Bioland supplies it country, zoom, and centre coordinates.
_Avoid_: biodiversity API, occurrence service.

**Search index** (`content`):
The Search API index, named `content`, over Content nodes. Bioland injects `field_order` into it and
configures its facets (content type term, language). The backend is the Search API database backend,
not Solr.
_Avoid_: Solr index, search core.
