---
status: accepted
date: 2026-08-03
deciders: [randy]
context: system-wide
code-path: src/Form/
origin: planning
plan: bl2-mega-menu
---

# 0005. Component menu authoring surface

Editors turn a menu link into a Bioland mega-menu component by hand-typing `bl2-component-*`
classes into the class textfield contrib `menu_link_attributes` 1.7 adds, stored in
`menu_link_content.link.options.attributes.class`. Hand-typing is undiscoverable and error-prone:
there is no list of valid tokens, and a typo fails silently in the frontend.

We add a dedicated add route, `/admin/structure/menu/manage/{menu}/add-component`, with a thin
form class registered as entity-form operation `component`; mode application still flows through
the existing alter layer. The picker owns only the component token (canonical `bl2-` prefix) and
merges it back into the class list, preserving every other token verbatim. Its options come from a
hardcoded PHP registry narrowed to BSL-appropriate components on `is_biosafety_land` sites; an
unknown component-shaped token surfaces as a preserved, disabled "Legacy" option.

## Considered Options

- Mode flag (query marker) on the core add form. Rejected: overloads the regular form's request
  handling with a second meaning.
- Type-chooser interstitial ahead of the regular add form. Rejected: slows the common,
  non-component path for every editor.
- In-form toggle switching the regular add form into component mode. Rejected: the regular form
  gains a control it does not otherwise need.
- Config-object option list instead of a PHP constant. Rejected: creates a second source of truth
  alongside the registry.

## Amendment (2026-08-06): guided form, hidden attributes

Editor feedback on the first shipped form: the collapsed per-option description list read as a
second, meaningless "Mega-menu component" box, and exposing the raw Attributes fieldset invited
the exact hand-typing this surface exists to remove. The form is now fully guided:

- The Attributes box is hidden (`#access` FALSE, not removed — the contrib entity builder still
  round-trips every class the picker does not own through the hidden textfield). The
  `component_menu_show_attributes` admin setting (default off) opts a site back in.
- The presentation classes the frontend styles a section by become form controls, each offered
  only where its Vue component actually reads it: a Show thumbnails checkbox (`bl2-show-thumbs`,
  legacy `mm-show-thumbs` read but normalized on save) gated to Content Type and All Content
  Types; a Mega menu columns select (`bl2-2x/3x/4x/5x` and their `-xl` viewport variants — 5
  triggers the Content Type card view); and a Maximum rows per column select
  (`bl2-ct-max-row-per-column-<n>`) gated to Content Type. A token never survives a save onto a
  component that cannot read it.
- The Content Type Listing component is relabelled "Content Type".
- The picker's description shows one sentence for the selected component via `#states`; the
  details list is gone.
- The Content Type Listing component gets a second select of published content types (the same
  set the mega-menu settings form configures under Content Type Menus), writing the
  `bl2-content-type-<slug>` binding class the frontend reads. Both token families are now owned
  by the registry; everything else still survives verbatim.
- BSL sites offer only the Content Type Listing, mirroring the BSL mega-menu settings form, which
  exposes only the Content Type Menus section. Non-BSL sites keep the full list.
- Title and Link prefill from the selection (JS, empty-field-only, always overwritable); Link
  defaults to `<nolink>` on add, matching every existing component link.

## Consequences

- The picker list can drift from `bioland-head`'s actual components; mitigated by a pinning unit
  test and a sync-checklist docblock. Drift fails benignly — the frontend renders an empty section
  for an unrecognised class.
- Regular (non-component) menu links must render byte-identically after this change; verified by
  later tasks in this plan.
- `menu_link_attributes` becomes a declared module dependency in `bioland.info.yml`.
