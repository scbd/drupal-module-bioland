# PR Seam Options: master..HEAD decomposition

Status: FINAL after three devil's-advocate rounds (codex, agy, grok, plus seam-critic in round 1). Branch under analysis: `mega-menu-dev` @ `ab085c9`. Round-3 re-cuts are folded into §3/§4 and dispositioned in §6.

## 1. Context

- Merge-base with `master`: `a2d5c2f`. `master..HEAD` = 206 commits, ~172k insertions across 277 files.
- Two distinct commit populations:
  1. **latest-dev gap** — `master..origin/latest-dev` = 170 commits. Oldest unique commit is `0bc7359` (BL-605 library/service wiring); newest is `0bb779b`. Already-Jira-tracked, already-PR-reviewed work merged to `latest-dev` (the deployed branch) but never promoted to `master`. Impl-only size: ~16,271 insertions across 67 files (full diff ~139k incl. `translations/*.po` and `yarn.lock`).
  2. **mega-menu/theming tip** — `origin/latest-dev..HEAD` = 36 commits. The 36 commits were built as two largely independent tracks (component-menu and theme) on parallel feature branches, joined by merges (`1919104`, `f5a0341`, `3b2db7f`, `c11b8ce`, `0bc68a4`). Impl-only size: ~5,496 insertions across 26 files (excludes tests, docs, .po, yarn.lock; the earlier 5,609/27 figure wrongly counted a Jest test as implementation).
- **In-flight stack (verified via `gh pr list`):** open draft PRs #26, #28, #29, #30, #31 already seam the early component-track tip onto `mega-menu-2026-08-05` (= `latest-dev` + 2, merge-base `0bb779b`): #26 = `menu_link_attributes` dependency (`b8b97f0`), #28 = component registry (`716179e`), #29 = component-mode form service (`1d2d29a`), #30 = edit-detection flip (`e686fdf`), #31 = dedicated add flow (`c6b7c0a`). Any option that ignores these duplicates open PRs.
- Update-hook state: `origin/latest-dev` pins max hook **9078** (`SearchApiConvergenceHookTest` asserts `max === 9078`); HEAD adds **9079** in `includes/bioland.install.helpers.inc`. `f48e6fe` introduces `bioland_update_9079()`; `f797bb2` later **mutates the same function** (adds `clear('theme.mega_menu.forums')`). Because a site records schema version 9079 the first time the hook runs, the hook cannot be grown across two shipped seams.

Sizing doctrine (create-prs-for-code-base): ~200 implementation LOC per PR, 400 hard ceiling; tests/docs/generated files exempt; PRs chained, draft, one logical unit each, valid running state at every PR head. SCBD repo: every PR pairs with a Jira ticket.

## 2. Options

### Option A (Recommended): continue the in-flight stack on latest-dev, two parallel sub-chains, promotion separate

- Keep `latest-dev` (via `mega-menu-2026-08-05`) as the review/deploy base. Continue and extend the existing open PR stack (#26/#28/#29/#30/#31) rather than opening duplicates.
- Restructure the tip into **two contiguous sub-chains off the sync point**: a component-menu chain and a theme chain, matching the real parallel history. The theme schema/contract work has zero code dependency on the component-menu PRs, so chaining them serially fabricates rebase coupling (any change to a component PR would otherwise invalidate every theme PR behind it).
- One convergence PR (CKEditor colour + the single `bioland_update_9079` hook) bases on **whichever family tip lands later**. Docs land last.
- **`latest-dev -> master` promotion is NOT part of this feature series.** It is a separate, explicitly-approved release reconciliation — a verbatim merge of `origin/latest-dev` — presented as ceiling-exempt with rationale: all 170 commits carry prior PR review (PR #17–#24 etc. into `latest-dev`) plus production soak, and GitHub's UI cannot render the ~139k-line diff anyway, so a "review PR" for it would be theater. The promotion PR body lists the constituent merged PRs and BL tickets as the review record. It can happen before, during, or after the feature chains; nothing in the chains depends on `master`.
- The three biggest classes ship **whole**, each as a documented, justified ceiling breach (see §3): `BiolandComponentMenuFormMode` (644), `BiolandThemeForm` (~812 across its three commits), `BiolandComponentMenuOverview` (814). Hunk-splitting a single cohesive class mid-method produces intermediate heads that cannot parse or pass tests; the ceiling exists to bound review effort, and a fictional split does not reduce it.

### Option B: seam everything from master directly

Re-cut the whole `master...HEAD` diff into thematic PRs each under 400 impl LOC. At ~16k impl LOC this is 40–80 PRs of re-review of already-reviewed, already-deployed code, with nearly every intermediate head invalid (interleaved version bumps, hook renumbering, tests pinned to hook maxima). Rejected.

### Option C: single 19-PR serial daisy-chain based on a master sync PR (the previous draft's recommendation)

PR 0 merges `latest-dev` into `master`, then all tip PRs chain serially 1→14 with hunk-level splits at the oversized commits. Rejected: PR 0 inside the series violates the series' own review contract (codex #1); serial chaining fabricates dependencies between the independent component and theme tracks (agy #1, grok #2); the prescribed hunk splits leave single classes at 644/814 LOC anyway and several slices cannot parse or run (codex #3, agy #2/#3, grok #5); and it silently duplicates the five open PRs (grok #1).

**Recommendation: Option A.** It honors the doctrine where the doctrine buys assurance (real seams, real running states, ceiling where a split is honest), documents the breaches where a split would be fictional, and does not duplicate in-flight review.

## 3. Seam table (Option A)

Two sub-chains off the sync point (`mega-menu-2026-08-05` / `latest-dev`), then a convergence PR and docs. All draft. LOC = implementation only (excludes tests, docs, .po, yarn.lock). Branch ticket ids are `BL-XXX` placeholders; existing PRs keep their branches.

**Component chain**

| # | Branch / PR | Base | Content | Impl LOC | Scope | Coupling risks |
|---|-------------|------|---------|----------|-------|----------------|
| C1 | #26 `chore/p01-02-declare-menu-link-attributes` (open) | sync point | `b8b97f0` | ~1 | Declare `menu_link_attributes` dependency (info.yml only; composer.json deliberately untouched) | Site enable/update fails without the contrib module until it is deployed — accepted, documented in PR body |
| C2 | #28 `feature/p01-03-component-registry` (open) | C1 | `716179e` | 396 | Mega-menu component registry + token rules | Near ceiling; consumed by everything downstream |
| C3 | #29 `feature/p02-01-component-mode-service` (open) | C2 | `1d2d29a` whole | 774 — **justified ceiling breach** | Inert component-mode form service, incl. `bioland.services.yml`, `bioland.module` wiring, `bioland.libraries.yml` + `css/bioland-component-menu.css` (the library the class attaches; CSS-only at this SHA — **no JS exists here**, the JS file arrives in `df26462`) | `BiolandComponentMenuFormMode.php` is 644 LOC of one cohesive class; splitting it by hunk yields unparseable heads, and splitting off the library fatals every attach (`bioland/component_menu_form`). Ships whole with justification in PR body |
| C4a | #30 `feature/p03-02-edit-component-detection` (open) | C3 | `e686fdf` | ~2 | `EDIT_DETECTION` FALSE→TRUE | None |
| C4b | #31 `feature/p03-01-add-component-route` (open) | **C4a** (re-based from C3 in round 3 — `#31`'s branch is NOT currently descended from `#30`'s; verified `git merge-base --is-ancestor origin/feature/p03-02-edit-component-detection origin/feature/p03-01-add-component-route` = 1. Re-target #31's base on GitHub, do not rewrite pushed history: land #30 first, then merge it into #31's branch) | `c6b7c0a`, `dc7af2e` (topological order after `e686fdf`), **plus** `src/Access/BiolandComponentMenuAccessCheck.php` and the `_bioland_component_menu_enabled` routing requirement pulled forward from `a47532d` via path-cut/hand-edited hunk | ~230 | Dedicated add flow: routing, `bioland.links.action.yml`, `BiolandMenuController` stub, link form | Without the pull-forward, the privileged add-component route ships gated only by `_entity_create_access` — the access check must travel with the route (or the exposure is documented and accepted; pull-forward recommended) |
| C5 | `feat/BL-XXX-component-link-form-guidance` | C4b | `df26462` **whole** (both `BiolandComponentMenuFormMode.php` and `BiolandComponentRegistry.php`, plus `js/bioland-component-menu-form-1-1-6.js` + its `bioland.libraries.yml` entry and `css/bioland-component-menu.css`) | ~507 — **justified ceiling breach** | Guided component menu link form + BSL narrowed to content types (one commit, one seam) | The round-2 C5/C6 path-split is **withdrawn**: the FormMode class in `df26462` calls `BiolandComponentRegistry::findContentTypeBindings()`, `mergeContentTypeBinding()`, `contentTypeBindingToken()` and `BiolandComponentRegistry::CONTENT_TYPE_SUFFIX`, none of which exist at `c6b7c0a` (verified `git show c6b7c0a:src/Service/BiolandComponentRegistry.php` — no matching API). Either ordering of the two files yields a fatal head. Ships whole |
| C6 | `feat/BL-XXX-thumbnails-column-widths-admin-toggles` | C5 | `a47532d` (minus the access-check paths already in C4b) | ~350 | Thumbnails and column-width controls, Content Type rename, admin toggles (title corrected in round 3 — it was swapped with C7); the `component_menu_show_attributes` key lands **as complete as the history allows**: schema + `config/install` default + admin form + service read in one PR. `a47532d` touches no install helper (verified `git show --name-only a47532d | grep helpers` = none) — the seeding hook is `bioland_update_9079`, which ships in X1, so **existing sites stay unseeded between C6 and X1** while fresh installs are correct. Release-note this gap | Config-key contract must not be split across seams; the backfill half is bounded by the once-only hook rule |
| C7 | `feat/BL-XXX-per-component-controls-audit` | C6 | `4db1741` (per-component presentation controls audited against the frontend); `f48e6fe` **minus** `includes/bioland.install.helpers.inc` 9079 hunks and the `SearchApiConvergenceHookTest` 9079 assertion (those move to X1) | ~250 | Per-component presentation controls audited against the frontend | a47532d + 4db1741 = 621 impl across C6/C7; ships with `SearchApiConvergenceHookTest` still pinning 9078 |
| C8 | `feat/BL-XXX-add-flow-rename-show-arrow` | C7 | `12ff4d4` **minus** `includes/bioland.install.helpers.inc` (verified: `git show 12ff4d4:includes/bioland.install.helpers.inc` already contains `bioland_update_9079()` at line 117 — cutting the whole tree here breaks the 9078 pin, since `allUpdateHookNumbers()` scans every `includes/bioland.install.*.inc`) | ~180 | Rename to "Add Mega Menu component", Show Arrow control | `12ff4d4` also touches ThemeForm-adjacent strings — component-side paths only via path-cut; theme-side residue goes to X1 |
| C9 | `feat/BL-XXX-menu-overview-service` | C8 | `5a43914` path-cut: `src/Service/BiolandComponentMenuOverview.php` **whole** + its `bioland.services.yml` entry, `bioland.module` hook wiring, `bioland/menu_overview` library and `css/bioland-menu-overview.css`, **plus** the `tests/stubs/Drupal/Core/StringTranslation/StringTranslationTrait.php` escaping fix (the `bioland.module` hunks are **hand-applied, overview wiring only** — `5a43914`'s parent is `572102c`, so a whole-file cut drags X1's CKEditor attach forward into the component chain) and the one-line `BiolandComponentMenuFormModeTest` expectation it forces | 814 — **justified ceiling breach** | Mega menu indicator column on the menu screen | The stub change escapes `@placeholder` substitutions; without the paired test-expectation update `BiolandComponentMenuFormModeTest` fails at this head (verified in `git show 5a43914 -- tests/Unit/Service/BiolandComponentMenuFormModeTest.php`) |
| C10 | `feat/BL-XXX-menu-controller-actions` | C9 | `5a43914` remaining paths: `BiolandMenuController` additions + `bioland.links.action.yml` "Add Mega Menu Child" + controller-side CSS | ~196 | Add Mega Menu Child action | Honest split of `5a43914`; no cross-file API dependency on C9 |

**Theme chain** (bases on the sync point, parallel to the component chain)

| # | Branch | Base | Content | Impl LOC | Scope | Coupling risks |
|---|--------|------|---------|----------|-------|----------------|
| T1 | `feat/BL-XXX-theme-config-schema` | sync point | `f6dbcfe`, `2d548f7`, `d5d4f77` (schema + `BiolandThemeContractTest` — it is schema+test, not docs) | 229 | Theme config schema + pinned PHP contract | Contract consumed by T2–T5 |
| T2 | `refactor/BL-XXX-home-widget-registry` | T1 | `dc465d4`, `e9a0ccb`, `5c32fce` | 441 — **acknowledged ceiling breach** (407+/34−; alternatively land the −34 refactor as a separate micro-PR first to bring the add under 400) | Shared flavor-aware home widget registry | T5 reads registry |
| T3 | `feat/BL-XXX-dmsm-config-service` | T2 | `4bb7bab` path-cut: `src/Service/BiolandDmsmConfigService.php` + its services.yml entry + outage fallback | ~153 | DMSM config service, seed/outage fallback | Must land **before** T4 — the form `use`s and `instanceof`s this service |
| T4 | `feat/BL-XXX-theme-tab` | T3 | `4bb7bab` remaining paths, `39c6c57`, `69e5983` | ~812 (`BiolandThemeForm` final) — **justified ceiling breach** | Theme tab authoring `bioland.settings.theme` | Single `buildSectionForm()` holds every section; a "sections 1/sections 2" hunk split is a mid-method cut — ships whole |
| T5 | `feat/BL-XXX-flavor-theme-defaults` | T4 | `f797bb2` **minus** `includes/bioland.install.helpers.inc`, `SearchApiConvergenceHookTest`, **and its `component_menu_add_enabled` label renames** in `src/Form/BiolandAdminSettingsForm.php` + `config/schema/bioland.schema.yml` (all move to X1) | ~165 | Flavor-specific theme defaults, silent seed fallback, retire Show Forums | Touches T2 registry + T3 service. The excluded renames retitle `component_menu_add_enabled` to 'Enable Mega Menu components' — that key does not exist on the theme chain (it lands in C6/`a47532d`), so cutting those paths onto T4 either fails or clobbers T4 with unlanded component settings |

**Convergence + docs**

| # | Branch | Base | Content | Impl LOC | Scope | Coupling risks |
|---|--------|------|---------|----------|-------|----------------|
| X1 | `feat/BL-XXX-ckeditor-colour-and-update-hook` | **whichever family tip (C10 or T5) lands later**, with the other family merged in | `572102c` (CKEditor paths); `bioland_update_9079()` in its **final form** — path-cut `includes/bioland.install.helpers.inc` + `SearchApiConvergenceHookTest` from `f797bb2`'s tree; `0bc68a4`'s merge-resolution content (routing/schema reconciliation, `tests/Unit/TranslationCatalogIntegrityTest.php`, `translations/*.po`) cut from HEAD; **plus** the `component_menu_add_enabled` label renames excluded from T5 and the theme-side string residue excluded from C8 | ~210 | CKEditor content styles follow authored primary colour; the ONE shipped 9079 hook seeding component-menu config, converging Search API v2, and clearing `theme.mega_menu.forums` | `572102c` also smuggles FormMode arrow-preview colour and ThemeForm fallback edits — those hunks are legitimately convergence work (they read theme config) and stay here, documented in the PR body. The hook must not appear in any earlier seam |
| D1 | `docs/BL-XXX-component-menu-adrs` | any time after sync point | `f0be261`, `11fb497`, `ea40bbd`, `5eb3f69` | 0 (docs only) | ADR 0005 + theme-authority ADR | None (`d5d4f77`→T1, `b8b97f0`→C1: both removed from this seam) |
| D2 | `docs/BL-XXX-docs-refresh` | X1 | `aab134b`, `de75569`, `ee5d528`, `ab085c9` | 0 (docs only) | README, architecture/PRD/ADR refresh, AGENTS.md promotion | Lands last by convention (documents all prior seams) |

**Promotion (outside the series):** `chore/BL-XXX-promote-latest-dev-to-master` — verbatim merge of `origin/latest-dev` (oldest unique commit `0bc7359`) into `master`. Explicitly ceiling-exempt, separately approved; PR body lists constituent PRs/BL tickets as the review record.

Merge commits in the tip (`c11b8ce`, `3b2db7f`, `f5a0341`, `1919104`, `0bc68a4`) are **never listed as source commits** — replaying a merge alongside its second parent's SHAs double-counts. `0bc68a4` is the exception in effect: its conflict-resolution content is real and is assigned explicitly to X1 (above).

Seam count: 10 component (C1-C10; the round-2 C5/C6 split collapsed to one seam, the two `5a43914` seams are now C9/C10 in the table) + 5 theme + 1 convergence + 2 docs = **18 seams**, of which 5 are already open (#26, #28, #29, #30, #31), plus 1 separate promotion PR.

**The open PR heads are red today, and chaining the bases does not fix that.** Measured with `vendor/bin/phpunit --no-coverage` on 2026-08-24: `mega-menu-dev` HEAD is green (460 tests, 0 failures), but `#30`'s head fails 6, and `#31`'s head fails 12 (8 after `#30` is merged into it). The cause is the travel-with rule being violated in the other direction — the branches carry **test expectations that belong to later seams**. Example: `BiolandComponentRegistryTest::testOptionsForBslSite` on `#31` expects the single narrowed `'bl2-component-content-type' => 'Content Type'` option that `df26462` (C5) introduces, while the branch's registry still returns the full five-option map. Every seam must ship its tests *with* its implementation, not ahead of it; the open PRs need their test files re-cut to the seam they belong to before any of them can satisfy the "valid running state at every PR head" contract.

## 4. Split prescriptions and travel-with rules

Never rewrite pushed history. Path-level re-cuts only: `git checkout <sha> -- <paths>` onto the seam branch. No hunk-level (`git add -p`) splits of single classes — every previously prescribed hunk split (old 3a/3b core, 7a/7b sections, 13a slice) is withdrawn; oversized cohesive classes ship whole with a justified-breach note in the PR body.

**Shared-file clobber hazard (mandatory reading before any path-cut).** The two tracks accreted changes into the same files: `config/schema/bioland.schema.yml`, `bioland.routing.yml`, `bioland.services.yml`, `bioland.module`, `src/Form/BiolandAdminSettingsForm.php`, `tests/Unit/TranslationCatalogIntegrityTest.php`, and all `translations/*.po`. Checking out a component-track SHA's version of such a file onto a branch that already carries theme-track content (or vice versa) **deletes the sibling work** — verified: `git checkout a47532d -- config/schema/bioland.schema.yml` on a theme-bearing base removes the entire `theme:` schema (83 deletions). Rule: for any file both tracks touch, either (a) cut the path from the **FINAL tree (HEAD)** onto a base that already contains all sibling work, or (b) apply the specific hunks by hand. Never cut a mid-history SHA's version of a shared file onto a branch missing sibling changes.

**Post-merge trees are the same hazard in the other direction (round 3).** `f48e6fe`, `12ff4d4`, `f797bb2`, `572102c` and `5a43914` all sit on or after the `0bc68a4` merge, so *their* trees already contain the sibling family's work. Verified examples: `git show f48e6fe:bioland.routing.yml` declares the `bioland.settings.front_end.theme` route to `BiolandThemeForm` while `a47532d`'s does not — a whole-file cut onto C6/C7 imports a route whose form class has not landed; `git show 12ff4d4:includes/bioland.install.helpers.inc` already defines `bioland_update_9079()`. For every shared file at these SHAs the prescription is **hand-apply the specific hunks**, never a whole-file `git checkout <sha> -- <path>`. The table rows name the specific exclusions; where a row says "minus X", the exclusion is mandatory, not advisory.

Travel-with rules (each seam is invalid without them):

- **`.po` catalogs + `TranslationCatalogIntegrityTest`:** nearly every impl commit updates `REQUIRED_MSGIDS` and all 67 `translations/*.po`. The msgid additions and catalog updates travel **with the seam that introduces the strings** (PHPUnit fails otherwise). They are LOC-exempt but never optional. The test file is a shared-file clobber vector — hand-apply its hunks per seam.
- **`bioland.libraries.yml` + CSS/JS assets travel with the class that attaches the library.** A class shipping ahead of its library fatals on attach; a library entry shipping ahead of its asset 404s. (C3: `component_menu_form` + its CSS; C5: the JS file + its entry; the overview library + `css/bioland-menu-overview.css` travel with `BiolandComponentMenuOverview` below.)
- **Config keys land complete:** schema entry + `config/install` default + admin-form element + service read + install-helper backfill in one PR (the `component_menu_show_attributes` lesson — codex #4).
- **Update hooks ship once, final-form, last.** `bioland_update_9079()` appears in exactly one seam (X1), cut from `f797bb2`'s tree, after both families' config surface exists. Earlier seams ship with `SearchApiConvergenceHookTest` still pinning 9078.

- **`tests/stubs/**` changes travel with the test expectations they force.** `5a43914`'s `StringTranslationTrait` stub now escapes `@placeholder` substitutions, which flips one `BiolandComponentMenuFormModeTest` assertion from `bl2-component-<script>` to `bl2-component-&lt;script&gt;`. Stub and expectation ship in the same seam (C9).
- **Commits whose two classes call each other ship whole.** `df26462` is the worked example: no path ordering of `BiolandComponentMenuFormMode.php` and `BiolandComponentRegistry.php` yields a parseable, test-passing head, so it is one seam (C5) with a justified breach rather than two fictional ones.

`5a43914` is cut into C9 (the 814-LOC `BiolandComponentMenuOverview` whole, with its service entry, hook wiring, library and CSS — justified breach) and C10 (`BiolandMenuController` additions + `bioland.links.action.yml` + controller-side CSS, ~196). Both now appear as rows in the §3 table; the earlier "17 vs 19" ambiguity is resolved at **18 seams**.

## 5. Ticket obligations (SCBD / Jira)

Per SCBD convention every PR pairs with a Jira ticket in the BL project (placeholders only, do not create yet):

- Promotion: chore ticket "Promote latest-dev to master" (references BL-604..BL-834 as prior art), flagged as separately-approved release reconciliation.
- C1–C10: feat tickets under the mega-menu/component-menu epic (the five open PRs may already have tickets — reuse, do not duplicate).
- T1–T5, X1: feat/refactor tickets under the theming epic.
- D1, D2: docs tickets.
- Each ticket sized to its PR; the five justified-breach PRs (C3, C5, C9, T2, T4) note the breach and rationale in the ticket. Worklog + Peer Review transition on each status change; ticket id replaces `BL-XXX` before push.

## 6. Challenged and held

High-impact round-1 objections **not** folded as re-cuts, with disposition:

| Objection | Critic | Disposition / rationale |
|-----------|--------|-------------------------|
| `BiolandComponentMenuFormMode` at 644 LOC busts the ceiling; no compliant split exists | codex #3, agy #7, grok #5 | **Held: ship whole (C3) as a documented justified breach.** Hunk-splitting one cohesive class yields heads that cannot parse or pass `BiolandComponentMenuFormModeTest`; a fictional split reduces nothing |
| `BiolandThemeForm` (~812) cannot be section-split; one `buildSectionForm()` holds every section | grok #5 | **Held: ship whole (T4) as a justified breach.** The real split — the DMSM service — is kept and reordered to land first (T3) |
| `BiolandComponentMenuOverview` at 814 LOC is 203% of ceiling under any path split | agy #3, grok #5 | **Held: ship whole as a justified breach**, with its service wiring, hook, library, and CSS in the same PR; the controller+action+CSS (~196) remains the honest split |
| T2 home-widget registry is 441, over ceiling | codex #2 | **Accepted breach** (documented in PR body), with the noted alternative of landing the −34 refactor as a separate micro-PR first |
| `b8b97f0` declares `menu_link_attributes` with no composer contract, before any consumer | grok #16 | **Accepted as-is (C1 = open PR #26).** The commit deliberately leaves composer.json untouched; the deploy-ordering exposure is documented in the PR body rather than re-cut |
| Docs "must land last" is chain-by-construction, not a dependency | grok #18 | **Accepted (kept by convention).** D1 (ADRs) is freed to land any time; only D2 (refresh documenting the finished state) stays last — zero cost, avoids docs describing unlanded behavior |
| Add-component route ships without `_bioland_component_menu_enabled` | grok #8 | Primary disposition is a re-cut (pull the access check forward into C4b); the documented-exposure fallback is retained only if the pull-forward's routing hand-edit proves error-prone in practice |

Round-3 objections and disposition:

| Objection | Critic | Disposition / rationale |
|-----------|--------|-------------------------|
| C5/C6 path-split of `df26462` produces a fatal head either way (FormMode calls registry API introduced in the same commit) | codex #1, agy #1 | **Re-cut: collapsed to a single seam C5** shipping `df26462` whole (~507) as a justified breach |
| C4a is orphaned — C4b/C5 based on C3, so `e686fdf`'s edit-detection flip never reaches downstream seams; `#31`'s branch is not descended from `#30`'s | codex #2 | **Re-cut: C4b re-bases on C4a.** Land #30 first, then merge it into #31's branch and re-target #31's base on GitHub — never rewrite pushed history |
| `f797bb2` also renames `component_menu_add_enabled` labels, which do not exist on the theme chain | agy #2 | **Re-cut: those two paths excluded from T5, assigned to X1** |
| Seam count 17 vs 19 contradiction; C10/C11 absent from the §3 table | codex #3, agy #3 | **Fixed: overview seams are now table rows C9/C10; total is 18**; X1 bases on C10 or T5 |
| `StringTranslationTrait` stub change forces a `BiolandComponentMenuFormModeTest` expectation update | agy #4 | **Re-cut: both travel together in C9**; travel-with rule added in §4 |
| C5's JS calls `once()`, which AGENTS.md forbids outright | codex #4 | **Rejected — the rule was wrong, not the code.** `js/bioland-component-menu-form-1-1-6.js:63` uses Drupal core's `once()` with a `core/once` library dependency, which is the correct API on Drupal 10/11; only jQuery's removed `.once()` plugin is invalid. AGENTS.md has been corrected accordingly; C5 ships unchanged |
| `12ff4d4` (C8) still carries `bioland_update_9079()` in `includes/bioland.install.helpers.inc`, breaking the 9078 pin | grok #2 | **Re-cut: C8 excludes `includes/bioland.install.helpers.inc`** (verified line 117 at that SHA) |
| `5a43914`'s parent is `572102c`, so cutting `bioland.module` whole drags X1's CKEditor attach into C9 | grok #3 | **Re-cut: C9 hand-applies the overview-only `bioland.module` hunks** |
| Post-merge SHAs (`f48e6fe`, `12ff4d4`, `f797bb2`, `572102c`, `5a43914`) carry the sibling family's content; whole-file cuts import unlanded work (e.g. the `BiolandThemeForm` route at `f48e6fe`) | grok #4 | **Re-cut: §4 now mandates hand-applied hunks for shared files at these SHAs**, with the verified examples |
| C6's "install-helper backfill in one PR" is false — `a47532d` touches no helper; the seeding hook is in X1 | grok #5 | **Fixed and disclosed:** existing sites stay unseeded between C6 and X1; fresh installs are correct; release-noted |
| C6/C7 scope titles were swapped relative to the SHAs they cite | grok #6 | **Fixed:** C6 = `a47532d` (thumbnails/column widths/admin toggles), C7 = `4db1741` (per-component controls audit) |
| `#26` and `#28` both base on `mega-menu-2026-08-05`, so the stack is not actually chained C2-on-C1 | grok #7 | **Accepted with an action:** re-target #28's base to #26's branch on GitHub (base change only, no history rewrite); patch-ids confirm the SHAs are equivalent to the local ones |
| Open PR head OIDs (`d429224`, `bba6494`, `62d1cc1`, `72f4dbc`, `041a4db`) differ from the local rebased SHAs quoted in §3 | agy #5 | **Accepted as-is.** The doc quotes local `mega-menu-dev` SHAs; remote PR heads are the pre-rebase equivalents. Operators comparing the two should expect the mismatch |

## 7. Open questions

1. Confirm whether the five open PRs already have BL tickets, and whether `mega-menu-2026-08-05` or `latest-dev` proper should be the canonical sync point going forward.
2. Timing of the master promotion PR relative to the feature chains (it is independent; recommend early, so `master` stops drifting).
3. Whether staging processes require the hook-bearing X1 to deploy in the same release as C7/C8 (the seeded `component_menu_*` config) — if staging deploys per-PR, X1's hook is the only update-path event and must be release-noted.
