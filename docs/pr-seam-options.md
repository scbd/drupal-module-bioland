# PR Seam Options: master..HEAD decomposition

Status: FINAL after three devil's-advocate rounds (codex, agy, grok, plus seam-critic in round 1). Branch under analysis: `mega-menu-dev` @ `ab085c9`. Round-3 re-cuts are folded into §3/§4 and dispositioned in §6.

## Decision v2 (2026-08-26): everything chunked — no bulk promotion, L-series first

Supersedes the 2026-08-25 promote-first decision. The single promotion PR (#32, ~139k lines) was rejected as un-reviewable and closed without merging; the "ceiling-exempt reconciliation" framing in Option A below is retired.

1. **The 170-commit latest-dev history is decomposed too.** The "L-series" (§7) rebuilds the entire `origin/latest-dev` tree from bare `master` as 40 chained, reviewable draft PRs (~16.3k impl LOC total; the other ~123k lines are LOC-exempt travel: 67 `.po` catalogs, lockfiles, tests, docs).
2. **Strictly sequential, single open PR.** L1..L40 first, then the tip seams (§3: C1..C10, T1..T5, X1, docs). Each PR bases on the previous seam's branch; merges happen one at a time in order.
3. **Open PRs #26–#31** are retargeted onto the L-series tip (L40's branch or the then-current `master`) once the L-series lands, then merged in C1..C4b order.
4. Tip-seam content, LOC accounting, and hard constraints (§4) are unchanged: the 9079 hook ships once, final-form, in X1 only; T5+X1 are an atomic merge unit.
5. **Breach ratification (2026-08-26):** the 14 whole-file ceiling breaches and L21's ~430 seam-level breach in §7 are accepted as-is (the three optional mechanical splits — search.inc 3-way, editor.inc, users.inc — were offered and declined); each PR body documents its breach.
6. **Intermediate heads are CI-green integration points, never deployment targets.** `drush updb` mid-series is unsupported; heads L26–L30 in particular carry a mandatory "never deploy/updb this head" note (convergence invariant holds from L31 onward).

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

**Read the "Content" column as provenance, not as a checkout instruction.** Each seam's content is taken from `HEAD`'s tree (§4); the SHAs listed name which paths and hunks belong to that seam and which are explicitly excluded from it.

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

**Promotion — RETIRED (2026-08-26).** The verbatim `latest-dev -> master` merge (PR #32) was rejected and closed without merging: a ~139k-line PR is un-reviewable and bypasses the chunking doctrine. Its content is now decomposed as the L-series in §7, which lands before every seam in this table. Read the §3 "Base" column as merge ORDER; the tip chain begins once L40 has landed.

Merge commits in the tip (`c11b8ce`, `3b2db7f`, `f5a0341`, `1919104`, `0bc68a4`) are **never listed as source commits** — replaying a merge alongside its second parent's SHAs double-counts. `0bc68a4` is the exception in effect: its conflict-resolution content is real and is assigned explicitly to X1 (above).

Seam count: 10 component (C1-C10; the round-2 C5/C6 split collapsed to one seam, the two `5a43914` seams are now C9/C10 in the table) + 5 theme + 1 convergence + 2 docs = **18 seams**, of which 5 are already open (#26, #28, #29, #30, #31), plus 1 separate promotion PR.

**Every commit in the 36-commit tip fails its own tests; only the final one is green.** Measured 2026-08-24 with `vendor/bin/phpunit --no-coverage` at each commit in `origin/latest-dev..HEAD`:

| Commit | Seam | Result |
|--------|------|--------|
| `716179e` | C2 (#28) | 166 tests, 3 failures |
| `1d2d29a` | C3 (#29) | 203 tests, 10 failures + 1 error |
| `e686fdf` | C4a (#30) | 205 tests, 6 failures + 1 error |
| `c6b7c0a` / `dc7af2e` | C4b (#31) | 230/231 tests, 8 failures + 1 error |
| `df26462` | C5 | 244 tests, 6 failures |
| `a47532d` / `4db1741` | C6 / C7 | 253/263 tests, 3 failures + 2 errors |
| `f48e6fe` / `0bc68a4` / `12ff4d4` | C7 / merge / C8 | 353-363 tests, 7 failures + 6-7 errors |
| `f797bb2` / `572102c` | T5 / X1 | 374/378 tests, 3 failures |
| `5a43914` | C9 / C10 | **460 tests, 0 failures** |

This is a property of the original development history, not of the PR branches or of this decomposition: the tip was written test-first-ish but only converges at the last feature commit, which lands the test-stub corrections and the remaining expectation updates. The PR branches inherit it faithfully — `#31`'s head fails 12, and 8 once `#30` is merged into it, exactly matching `c6b7c0a`'s own result.

**Consequence for this plan: seams must be built forward from the final tree, never replayed from historical SHAs.** Any seam whose content is `git checkout <mid-history-sha> -- <paths>` inherits that SHA's red state and cannot satisfy the "valid running state at every PR head" contract. The prescription in §4 for shared files — cut from HEAD — must be generalised to *all* files: a seam takes its paths from `HEAD`'s tree (which is green) and is verified with `vendor/bin/phpunit` before its PR opens. The historical SHAs stay in the table as the *provenance and boundary* of each seam's content, not as the thing to check out.

## 4. Split prescriptions and travel-with rules

**Build every seam forward from `HEAD`, never by replaying a historical SHA.** `HEAD` is the only green tree in the range (§3), so a seam that takes its content from `HEAD` starts valid and only has to be proven *sufficient*; a seam that checks out a mid-history SHA starts red and has to be *repaired*. The historical SHAs in the §3 table are the **provenance and boundary** of each seam — they say which paths and which hunks belong to it — not the tree to check out.

The per-seam recipe:

```bash
git checkout -b <seam-branch> <base-branch>     # base = previous seam, or the sync point
git checkout HEAD -- <paths for this seam>      # content comes from the green tree
# for a file the seam only partly owns, apply its hunks by hand from `git diff <base> HEAD -- <file>`
vendor/bin/phpunit --no-coverage                # MUST be green before the PR opens
npx jest --ci
```

The phpunit run is the gate, not a formality: it is what distinguishes a seam that is genuinely self-contained from one that is missing a dependency its neighbours were quietly providing. If a seam cannot go green without pulling in another seam's paths, that is the decomposition telling you the boundary is in the wrong place — move the boundary, do not weaken the gate. Repo CI already runs both suites on every `pull_request` (`.github/workflows/ci.yml`), so a red head cannot merge unnoticed either way.

**What build-forward costs, measured.** Because a seam takes its files at final form, every later refinement of a file collapses into the seam that first introduces that file. Verified on C2: cutting `src/Service/BiolandComponentRegistry.php` from `HEAD` onto the sync point is green (191 tests, up from 121 at the base, versus 3 failures when replaying `716179e`) — but the file lands at **797 LOC instead of 391**, because it absorbs the registry changes from `df26462`, `a47532d`, `4db1741`, `12ff4d4` and `5a43914`. Grouping every implementation file in the tip by the commit that first introduces it gives the real build-forward shape:

| Owning commit | Seam | Files | Final LOC |
|---------------|------|-------|-----------|
| `716179e` | C2 | 2 | 840 |
| `1d2d29a` | C3 | 4 | 2,334 |
| `c6b7c0a` | C4b | 3 | 374 |
| `df26462` | C5 | 1 | 88 |
| `a47532d` | C6 | 3 | 517 |
| `f48e6fe` | C7 | 1 | 192 |
| `5a43914` | C9/C10 | 2 | 841 |
| `f6dbcfe` | T1 | 2 | 724 |
| `dc465d4` | T2 | 2 | 904 |
| `4bb7bab` | T3/T4 | 2 | 1,292 |
| `572102c` | X1 | 1 | 82 |

So build-forward trades seam *count and size* for seam *validity*: roughly 11 content-bearing seams instead of 18, six of them over the 400 ceiling and one at nearly 6x it. That is the honest price of green heads, and it is worth paying — a 2,334-LOC PR that reviewers can actually check out and run beats four 500-LOC PRs that none of them can. Where a file genuinely evolves in separable stages, a seam may still be split by hand-applying its hunks (§4), but only if the resulting head passes `phpunit`; the measured history says most such intermediate states do not.

**The five open PRs (#26, #28, #29, #30, #31) predate this rule and carry mid-history content**, which is why their heads fail (#31: 12 failures, 8 after #30 merges in). They need re-cutting from `HEAD` on the same branches — new commits forward, never a force-push or a rewritten history — before they can go green. Their base wiring is enforced separately by `scripts/pr-stack/stack.yml` and the pr-stack-guard workflow.

Never rewrite pushed history. No hunk-level (`git add -p`) splits of single classes — every previously prescribed hunk split (old 3a/3b core, 7a/7b sections, 13a slice) is withdrawn; oversized cohesive classes ship whole with a justified-breach note in the PR body.

**Shared-file clobber hazard (mandatory reading before any path-cut).** The two tracks accreted changes into the same files: `config/schema/bioland.schema.yml`, `bioland.routing.yml`, `bioland.services.yml`, `bioland.module`, `src/Form/BiolandAdminSettingsForm.php`, `tests/Unit/TranslationCatalogIntegrityTest.php`, and all `translations/*.po`. Checking out a component-track SHA's version of such a file onto a branch that already carries theme-track content (or vice versa) **deletes the sibling work** — verified: `git checkout a47532d -- config/schema/bioland.schema.yml` on a theme-bearing base removes the entire `theme:` schema (83 deletions). Under the build-forward rule this hazard mostly dissolves — `HEAD`'s version of a shared file contains *both* tracks' work by definition. What remains is the opposite risk: cutting `HEAD`'s version of a shared file onto an early seam imports sibling content that has not landed yet. So for any file both tracks touch, **apply only this seam's hunks by hand** (`git diff <base> HEAD -- <file>` and take the relevant ones), never the whole file. The whole-file cut is safe only for files a single seam wholly owns.

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

## 7. L-series: decomposing the latest-dev tree into reviewable seams

Build-forward from bare `master`: seams are cut from the final `origin/latest-dev` tree (files land at final form), not by replaying the 170 commits. Produced 2026-08-26 by a measure/draft/verify/repair pass (measured inventory of 65 impl files / 16,291 impl LOC, 22 subsystems, 69 update hooks; two adversarial critics raised 16 objections, 1 High — the cross-include update-hook ordering violation — all folded or rejected with evidence in §8.1).


Chained: L1 bases on `master`; each L(n) bases on L(n-1)'s branch. Every head must pass `composer test`, `npx jest --ci`, `npm run lint:php`. Shared files (`bioland.module`, `bioland.install`, `bioland.services.yml`, `bioland.routing.yml`, `bioland.links.task.yml`, `bioland.libraries.yml`, `bioland.permissions.yml`, `bioland.info.yml`, `config/schema/bioland.schema.yml`, `config/install/bioland.settings.yml`) accrete — each seam carries only its subsystem's entries. bioland.module apportionments are now MEASURED line ranges, not estimates. Doctrine (folded from O1-alt + OBJ-6): intermediate heads are CI-green integration points, never deployment targets — `drush updb` mid-series is unsupported; the include order below is nonetheless fully topological so no head can fatal on updb, and the max-hook-converges invariant holds at every head from L31 onward.

### 7.1 Seam table L1–L40

| L# | Branch | Files (per-file LOC for >100) | Est impl LOC | Scope | Validity / coupling | Test-pin state |
|----|--------|-------------------------------|--------------|-------|---------------------|----------------|
| L1 | `chore/BL-XXX-01-skeleton-test-infra-ci` | bioland.info.yml (base, +fontawesome dep, NO `configure:` key — lands L8), bioland.module (shell: header only), bioland.install (shell: `bioland_requirements()` with fontawesome-ONLY `$contrib_modules` + empty `bioland_install()`), bioland.permissions.yml (`administer bioland settings`), composer.json, package.json, .github/workflows/ci.yml, .gitignore (+31 delta), .dockerignore, schema+settings skeletons, js/bioland-debug-logger-1-1-6.js, bioland.libraries.yml (`debug_logger`), bioland.services.yml (header). Travel (LOC-excl): yarn.lock, jest.config.js, phpunit.xml.dist, tests/bootstrap.php, tests/stubs/** (26), tests/Unit/SmokeTest.php, js debug-logger test (292), README.md (540), .github/copilot-instructions.md (222) | ~385 | Module shell + dual test harness + CI + debug-logger JS | Missing-src/ coverage risk disproven (see Critic dispositions OBJ-8). `bioland_requirements()` accretes: linkit entry lands L34 | SmokeTest only |
| L2 | `feat/BL-XXX-02-country-map-defaults` | src/Service/BiolandCountryMapDefaults.php (286). Travel: docs/COUNTRY_MAP_DEFAULTS.md, docs/EXAMPLE_GBIF_WIDGET.js | 286 | Static per-country GBIF map data | Zero deps; precedes L3/L19 | — |
| L3 | `feat/BL-XXX-03-settings-manager` | src/Service/BiolandSettingsManager.php, services entry, schema/settings keys (countries, home_widgets skeleton). Travel: test (171) | ~123 | Config access layer | Statically calls L2 | — |
| L4 | `feat/BL-XXX-04-translation-manager` | src/Service/BiolandTranslationManager.php (444), services entry. Travel: test (382) | ~448 | Translation-defaults core service | **BREACH: single file 444** | — |
| L5 | `feat/BL-XXX-05-translation-batch-entity-hooks` | src/Service/BiolandTranslationBatchService.php (206), services entry, bioland.module (entity insert/update hooks + 4 helpers, lines 358-462 = **105 measured**, was ~80), schema+settings `translation.*`, info.yml (+ANT×4). Travel: test (240), docs/adr/0003 | ~364 | Auto-create translation stubs + batch backfill | MUST precede L7 (ctor DI) | — |
| L6 | `feat/BL-XXX-06-settings-ui-chrome` | js/bioland-settings-toggle-1-1-6.js, css/bioland.admin.css (154), twig template, bioland.module (hook_theme, lines 463-481 = 19), libraries (`settings_toggle`, `admin`). Travel: test (237) | ~344 | Settings-page assets | Must precede L7 (`BiolandSettingsFormBase.php:194` attaches) | — |
| L7 | `feat/BL-XXX-07-settings-form-base` | src/Form/BiolandSettingsFormBase.php (300) | ~300 | Abstract per-tab form base | Requires L5 + L6 | — |
| L8 | `feat/BL-XXX-08-general-settings-tab-routing` | src/Form/BiolandGeneralSettingsForm.php (386), bioland.routing.yml (`bioland.settings`), links.task.yml (`bioland.settings_tab`), **info.yml `configure: bioland.settings` key lands here** (OBJ-2). General-tab schema/settings keys DEFERRED to L10 (O6 shave). Travel: BiolandSettingsFormTest (542), RoutingWiringTest (227) | ~400 | First route + General tab | At ceiling, no longer over; form tolerates missing keys one seam (getter fallbacks — same sanctioned pattern as L19) | RoutingWiringTest active: every route-adding seam adds its form class same-seam |
| L9 | `feat/BL-XXX-09-branded-menu-link` | src/Plugin/Derivative/BiolandMenuLink.php, bioland.links.menu.yml. Travel: test (202) | ~71 | Branded admin menu link | Route target exists (L8) | — |
| L10 | `feat/BL-XXX-10-field-functionality-services` | src/Service/BiolandFieldFunctionalityManager.php (142), src/Service/BiolandAdditionalTagDefaults.php (47), services entry, schema (`enable_*`, `field_visibility.*`, `additional_tags.*`, `debug_log_areas.*` + General-tab keys from L8) + settings defaults. Travel: tests (345, 130) | ~310 | JS-settings assembly + fixed tag map | ATD needed by fields.inc 9077 (L33) | — |
| L11 | `feat/BL-XXX-11-node-form-alter-core` | bioland.module (form_alter core incl **lolspeak-hiding lines 65-97 moved here from old L25** (O2), save-label rename, node-form gate, drupalSettings injection, advanced sidebar, langcode sidebar; + form_node_form_alter 337-357, module_implements_alter 482-499, node_content_form_submit 500-522 — ~271 measured), js/bioland-language-redirect-1-1-6.js (87), libraries (`language_redirect`) | ~362 | Node-form wiring backbone | Reads FFM (L10). form_alter accretes across L11/L12/L13/L14/L17/L25/L28 — see mandatory diff-check in §3 (OBJ-5) | — |
| L12 | `feat/BL-XXX-12-field-visibility-js` | js/bioland-field-visibility-1-1-6.js (261), library, attach lines (~4). Travel: test (563) | ~272 | Field-visibility behavior | — | — |
| L13 | `feat/BL-XXX-13-additional-fields-js` | js/bioland-additional-fields-1-1-6.js (570), library, attach lines. Travel: test (646) | ~581 | Additional-fields Vue-mount | **BREACH: single file 570** | — |
| L14 | `feat/BL-XXX-14-auto-summary-js` | js/bioland-auto-summary-1-1-6.js (679), libraries (`auto_summary` + `comprehensive_fields`), attach lines. Travel: test (1318) | ~697 | Auto-summary behavior | **BREACH: single file 679** | — |
| L15 | `feat/BL-XXX-15-field-visibility-tags-tabs` | src/Form/BiolandFieldVisibilityForm.php (132), src/Form/BiolandTagsForm.php, routing (2), tasks (2), permissions | ~236 | Two config tabs feeding L10 | — | — |
| L16 | `feat/BL-XXX-16-help-comments-form` | src/Form/BiolandHelpCommentsForm.php (373), routing+task, settings keys | ~397 | Help Comments tab | — | — |
| L17 | `feat/BL-XXX-17-help-comments-js` | js/bioland-help-comments-1-1-6.js (589), library (bundles admin.css), attach lines, schema `help_comments.*` (**70 measured**, schema.yml:91-163 — was ~30, O3). Travel: test (934) | ~668 | Cookie-persistent help blocks | **BREACH: single file 589** (seam restated 634→668) | — |
| L18 | `feat/BL-XXX-18-system-functions-tab` | src/Form/BiolandSystemFunctionsForm.php (258), routing+task, permissions. Travel: test (175) | ~276 | Batch translation UI | — | — |
| L19 | `feat/BL-XXX-19-home-widgets-form` | src/Form/BiolandHomeWidgetsForm.php (531), routing+task. Travel: test (268) | ~545 | Home Widgets tab | **BREACH: single file 531.** Schema lands L20 (getter fallbacks) | — |
| L20 | `feat/BL-XXX-20-home-widgets-js-wiring` | js/bioland-home-widgets-1-1-6.js, library, bioland.module (home-widgets portion of hook_page_attachments = **~12 measured** of lines 40-57, was ~30, O2), schema `home_widgets.*` (~130 measured, schema.yml:300-429) + settings defaults. Travel: js test (131) | ~221 | Site-wide homeWidgets drupalSettings | Calls `bioland.settings_manager` (L3) | — |
| L21 | `feat/BL-XXX-21-front-end-parent-and-general-tab` | src/Form/BiolandFrontEndGeneralForm.php (347), **src/Form/BiolandFrontEndRedirectForm.php (47) + route `bioland.settings.front_end` + parent front_end tab moved here from old L23** (OBJ-4: general/mega_menu tabs declare `base_route: bioland.settings.front_end`, links.task.yml:22/28 — parent must exist same-seam), route `front_end.general` + general tab, `config.promote_and_sticky_public` keys | ~430 | Front End parent + General tab | **BREACH: seam-level ~430, largest file 347** — forced: the general tab's base_route and the RedirectForm's redirect target (`front_end.general`) mutually require same-seam landing | — |
| L22 | `feat/BL-XXX-22-mega-menu-tab` | src/Form/BiolandMegaMenuForm.php (511), routing+task, schema `mega_menu.*` (**107 measured**, schema.yml:193-299 — was ~60, O3) + settings | ~640 | Mega Menu config | **BREACH: single file 511** (seam restated 599→640) | — |
| L23 | `feat/BL-XXX-23-home-page-tab` | src/Form/BiolandHomePageForm.php (312), routing (`front_end.home_page`) + task. Travel: test (253) | ~326 | Home Page heroes | Parent tab already exists (L21). Soft-deps with config/install fallbacks | — |
| L24 | `feat/BL-XXX-24-admin-tab` | src/Form/BiolandAdminSettingsForm.php (252), routing+task (admin tab weighted LAST), schema (`is_biosafety_land`, `countries`, `main_menu_lock`, debug flags). Travel: BiolandLocalTasksTest (147) | ~318 | Admin tab — final route/tab; routing.yml + links.task.yml reach final form | — | LocalTasksTest active |
| L25 | `feat/BL-XXX-25-admin-ux-hooks` | bioland.module (measured: menu_links_discovered_alter 12-33 (22), roles portion of page_attachments (~13), menu-link form_alter section 103-153 (51), menu_link_form_submit 523-548 (26), local_tasks_alter 549-571 (23), link_alter 572-627 (56), user-cancel hooks 650-675 (26), **views_pre_render 676-733 (58) + preprocess_html 734-763 (30) — both live no-op functions with fully commented bodies** = ~305), js/bioland-hide-bulk-actions-1-1-6.js (63), library. Travel: js test (152) | ~375 | Site-wide admin UX hooks | Under ceiling after lolspeak→L11 move (O2). Hand-split coordination with L20 on page_attachments | — |
| L26 | `feat/BL-XXX-26-dmsm-integration` | src/Service/BiolandDmsmConfigService.php (255), services entry, includes/bioland.install.dmsm.inc (120), bioland.install (first require_once + install call). Travel: test (212), docs/DMSM_COUNTRY_INTEGRATION.md, docs/adr/0002 | ~398 | DMSM country authority (9001/9023/9046) | First include seam. **PR body: intermediate heads L26-L30 must never be deployed/updb'd** (OBJ-6) | Convergence test withheld until L39 |
| L27 | `feat/BL-XXX-27-install-helpers-roles` | includes/bioland.install.helpers.inc (92), includes/bioland.install.roles.inc (271), install wiring | ~370 | Roles + shared `_bioland_require_module` (9006/9020/9032/9033/9045/9061/9073) | Max hook becomes 9073 (non-converging) — invariant hole L27-L30, disclosed in PR bodies | — |
| L28 | `feat/BL-XXX-28-install-editor` | includes/bioland.install.editor.inc (553), css/bioland.ckeditor.css, library (`ckeditor_content_styles`), bioland.module attach line (module:178), install wiring | ~648 | Full-HTML/CKEditor config (9016/9017/9022/9034/9038) | **BREACH: single file 553** (split option considered, declined — §2). Needs helpers (L27 ✓). Must precede L33 (fields 9024→`_bioland_configure_full_html_editor_toolbar`, fields.inc:393) | — |
| L29 | `feat/BL-XXX-29-install-form-display-jsonapi` | includes/bioland.install.form_display.inc (235), includes/bioland.install.jsonapi.inc (85), info.yml (+jsonapi_extras), install wiring | ~330 | Form displays (9027/9036/9053-9055) + JSON:API disable (9037) | **MOVED BEFORE search (O1): search.inc 9026 calls `_bioland_configure_langcode_form_display` (search.inc:959)** | — |
| L30 | `feat/BL-XXX-30-install-search-v1` | includes/bioland.install.search.inc (960), bioland.module (search_api_index_items_alter, lines 628-649 = **22 measured**, was ~40), install wiring | ~990 | Frozen SUPERSEDED v1 search replay (9011/9026) | **BREACH: single file 960 — rationale corrected (O5): frozen superseded v1 replay code, retained solely so historical hooks stay replayable (file header lines 10-17 forbids wiring it into new code); deliberately shipped whole as one frozen unit.** 3-way split (~421/~388/~190) documented and declined — §2/OQ1. After form_display (L29 ✓) | — |
| L31 | `feat/BL-XXX-31-install-search-v2` | includes/bioland.install.search.v2.inc (433), install wiring. Travel: **docs/adr/0004 (sole owner — O7)** | ~448 | Canonical v2 convergence (9059/9060/9064/9074) | **BREACH: single file 433.** After search v1 (converge fn calls v1's `_bioland_configure_search_api_index`/`_bioland_configure_facets`, search.v2.inc:383-384 — load-bearing pin). Max hook 9074 converges: **invariant holds at every head from here on (verified: L31-L38 max 9074/9077 both converge; L39 9078 converges)** | — |
| L32 | `feat/BL-XXX-32-install-users` | includes/bioland.install.users.inc (533), install wiring | ~548 | User provisioning/blocking (9021/9025/9030/9031) | **BREACH: single file 533** (split declined — §2). **MOVED AFTER search (O1): 9025 calls `_bioland_configure_search_api_index` + `_bioland_configure_facets` (users.inc:469,475)** | — |
| L33 | `feat/BL-XXX-33-install-fields` | includes/bioland.install.fields.inc (437), config/optional/field.storage + field.field yml, install wiring | ~500 | Field install/fixes + tag pinning (9004/9005/9007/9014/9024/9047/9077) | **BREACH: single file 437.** After editor (L28 ✓, fields.inc:393) + search.v2 (L31 ✓, fields.inc:434) + ATD (L10 ✓) | — |
| L34 | `feat/BL-XXX-34-install-linkit` | includes/bioland.install.linkit.inc (371), info.yml (+linkit), **bioland.install: linkit entry added to `$contrib_modules` in bioland_requirements() (OBJ-3)**, install wiring | ~390 | Linkit profile + widgets (9013/9018/9019) | **MOVED AFTER fields (O1): 9019 calls `_bioland_configure_content_fields` (linkit.inc:369)** | — |
| L35 | `feat/BL-XXX-35-install-content-types-core` | includes/bioland.install.content_types.inc PART A: `_bioland_normalize_langcode_for_translation` (33-52) + `_bioland_configure_content_types` (106-359) + hooks 9029/9072, install wiring | ~305 | Content-type terms + translations (O4 split A) | Include accretes across two seams — sanctioned by the plan's own model; only intra-file coupling (normalize↔configure_content_types) is same-seam; duplicate-function guards land L40 | — |
| L36 | `feat/BL-XXX-36-install-content-types-site` | content_types.inc PART B: `_configure_content_type_available_menus` (53-105) + `_status_by_site_type` (360-509) + `_system_pages_search_terms` (510-624) + `_weights` (625-668) + hooks 9028/9035/9039/9041 — file reaches final form, install wiring | ~395 | Site-type/menus/search-terms/weights (O4 split B) | Must precede L37 (menu 9056 calls `_configure_content_type_available_menus`, menu.inc:370) | — |
| L37 | `feat/BL-XXX-37-install-menu` | includes/bioland.install.menu.inc (436), install wiring | ~446 | Menu lock + Content link + ANT menu disable (9040/9048/9051/9052/9056-9058) | **BREACH: single file 436.** After content_types-B (L36 ✓) | — |
| L38 | `feat/BL-XXX-38-install-views` | includes/bioland.install.views.inc (456), install wiring | ~466 | Admin views (9042-9044/9049/9050) | **BREACH: single file 456** | — |
| L39 | `feat/BL-XXX-39-translation-catalog-import` | includes/bioland.install.translation.inc (529), bioland.install (final require_once — FINAL FORM). Travel (LOC-excl): translations/*.po (67), TranslationCatalogIntegrityTest (184), SearchApiConvergenceHookTest (156) | ~554 | PO import + terminal hooks (9002/9003/9012/9062/9063/9065/9071/9075/9076/9078) | **BREACH: single file 529.** Last include: hooks call converge fn (L31) + translation_manager (L4); .po same-seam | BOTH gate tests land: all 15 includes present, max hook===9078, 13 REQUIRED_MSGIDS |
| L40 | `test/BL-XXX-40-install-guard-tests-docs` | Travel only: InstallDuplicateFunctionsTest (212), DuplicateFunctionDeclarationTest (142), DuplicateFunctionTest (131), docs/CONTEXT.md, architecture.md, prd.md, INSTALL.md, UPDATE.md, docs/adr/0001, docs/screen-shots/bsl/*.pdf | 0 | Structural guard tests + remaining docs | Function requirements (users L32, content_types L35/36, menu L37, translation L39) all satisfied | Final head = full latest-dev tree |

### 7.2 Justified breaches (RATIFIED 2026-08-26 — accepted as-is)

Whole-file breaches (14 — content_types removed via O4 split):

| Seam | File | LOC | Split option |
|------|------|-----|--------------|
| L4 | BiolandTranslationManager.php | 444 | none (one class) |
| L13 | bioland-additional-fields-1-1-6.js | 570 | none (one behavior) |
| L14 | bioland-auto-summary-1-1-6.js | 679 | none |
| L17 | bioland-help-comments-1-1-6.js | 589 | none |
| L19 | BiolandHomeWidgetsForm.php | 531 | none |
| L22 | BiolandMegaMenuForm.php | 511 | none |
| L28 | bioland.install.editor.inc | 553 | exists (format-config vs migration helpers, ~305/~248) — DECLINED: one editor subsystem, atomic review preferred |
| L30 | bioland.install.search.inc | 960 | exists (3-way ~421/~388/~190 along file's own banners; internal call graph verified clean: only `_final_reindex`→`_ensure_tables`+`_clear_pending`, facets→processor_configs) — DECLINED: frozen superseded v1 replay code, shipped whole deliberately |
| L31 | bioland.install.search.v2.inc | 433 | none (serialized-config unit) |
| L32 | bioland.install.users.inc | 533 | exists (provisioning vs grant/block, ~270/~260) — DECLINED: one provisioning subsystem |
| L33 | bioland.install.fields.inc | 437 | none practical |
| L37 | bioland.install.menu.inc | 436 | none practical |
| L38 | bioland.install.views.inc | 456 | none practical |
| L39 | bioland.install.translation.inc | 529 | none (import fn + its hooks) |

Seam-level overages with no file >400: **L21 ~430** (forced same-seam by base_route/redirect mutual validity, OBJ-4) — now IN this table, not prose. L8 shaved to ~400 (at ceiling) via schema-key deferral to L10 (O6 — no longer an open question). Old L25 overage (O2, ~407) eliminated by moving lolspeak-hiding to L11.

### 7.3 Hook / test-pin prescription

- **Update hooks:** zero at heads L1-L25. From L26 each include lands final-form (partial-form for content_types across L35/L36 only). **Topological order pins (all verified against the measured call graph):** helpers (L27) before editor/jsonapi/linkit/search/views; form_display (L29) before search (L30); search (L30) before search.v2 (L31) and users (L32); search.v2 (L31) before fields (L33) and translation (L39); editor (L28) before fields (L33); fields (L33) before linkit (L34); content_types-B (L36) before menu (L37); translation (L39) strictly last (hook 9078). A `drush updb` at any include-seam head can no longer hit an undefined function.
- **Convergence invariant:** violated at heads L26-L30 (max hook 9046 then 9073, non-converging) — those five PR bodies MUST carry "CI-green integration point; never deploy or run updb on this head". Holds at every head L31+ (9074, 9077, 9078 all converge — verified in source).
- **bioland_form_alter / bioland.module hand-split guard (OBJ-5):** every seam touching bioland.module (L1, L5, L6, L11, L12, L13, L14, L17, L20, L25, L28, L30) carries a mandatory acceptance check: `git diff <head>..origin/latest-dev -- bioland.module` must consist solely of not-yet-landed hunks — no line at the head may differ from final form other than by absence.
- **SearchApiConvergenceHookTest + TranslationCatalogIntegrityTest:** ship ONLY in L39 (unchanged rationale).
- **BiolandSettingsRoutingWiringTest:** ships L8; route-adding seams (L15, L16, L18, L19, L21, L22, L23, L24) add form classes same-seam.
- **BiolandLocalTasksTest:** ships L24; L8-L23 add task entries in lockstep; admin task weighted last.
- **Guard tests:** ship L40. DuplicateFunctionTest's gapless check scans only bioland.install (no hooks there) — unaffected by the content_types two-seam accretion; at the L35 head the partial include is parse-valid and its hooks (9029/9072) call only same-half helpers.
- **Jest/PHPUnit floor:** unchanged (debug-logger pair + SmokeTest from L1).

### 7.4 Coverage check

All impl/config-impl files assigned to exactly one seam; content_types.inc assigned across exactly two accreting seams (L35+L36) whose measured halves (254+51 / ~341+hooks) sum to the file's 720; docs/adr/0004 now single-owner (L31). bioland.module apportionment re-measured: portions now sum to 763 exactly (shell 11, L5 105, L6 19, L11 ~271, L12/13/14/17 attach ~18, L20 ~12, L25 ~305, L28 attach ~2, L30 22 — reconciled against function boundaries at lines 17/40/64/337/361-448/468/492/505/529/554/577/633/656/669/682/740). Schema apportionment re-measured against mapping boundaries (help_comments 91-163=~70, mega_menu 193-299=~107, home_widgets 300-429=~130, translation 430-451=~22). Unassigned: LICENSE only (unchanged from a2d5c2f). Summed seam estimates ≈ 16,190 vs inventory implLoc 16,291; residual drift <1%, confined to yml-entry apportionments.

### 7.5 Totals

**40 seams** (39 impl-bearing + 1 test/docs-only; +1 vs draft from the content_types split). Summed reviewable impl LOC ≈ 16.2k vs the ~139k full-tree dump — reviewers see ~12%; the other ~123k is LOC-exempt travel (67 .po ≈ 108k → L39, lockfile/stubs → L1, tests ≈ 10.5k per-subject, docs ≈ 3.6k).

## 8. Open questions (L-series)

1. **Breach ratification (OBJ-9, mandatory before tickets):** one explicit user decision — accept the 14 whole-file breaches + L21's ~430 seam-level breach as listed in §2, OR permit final-form-preserving mechanical splits for the three files where a measured split exists (search.inc 3-way ~421/~388/~190; editor.inc ~305/~248; users.inc ~270/~260), adding up to 4 more seams. Recommended: accept §2 as-is — search.inc is frozen superseded replay code and the editor/users splits buy little.
2. `administer bioland translation settings` permission declared but route-unused (L18) — drop as separate cleanup, or wire to the System Functions route?
3. L19 home-widgets form one seam before its schema defaults (L20) — acceptable via getter fallbacks (same pattern now also used by L8→L10), or swap?
4. info.yml accretion (contribs per-seam, `configure:` key at L8) deviates from "final-form in seam 1" — confirm sanctioned (recommended; strictly safer, and OBJ-2/OBJ-3 depend on it).
5. BL-XXX ticket IDs: one ticket per seam vs umbrella — tracker's call.
6. Stale AGENTS.md reference to `js/hello.test.js` — doc fix candidate for L40.
(Old OQ4 requirements-check question: RESOLVED by OBJ-3 fold. Old OQ5 L8 overage: RESOLVED by O6 shave.)

### 8.1 Critic dispositions (L-series verification)

| Objection | Disposition | Why |
|-----------|-------------|-----|
| O1-cross-include-order-violations (High) | **FOLDED** — re-verified independently before folding: full cross-include scan reproduced all three live edges (users.inc:469/475→search.inc; linkit.inc:369→fields.inc; search.inc:959→form_display.inc) and confirmed the apparent counter-edges (helpers→translation 9062/9071, content_types→9071, search.v2→9025, search.inc:13→9064) are docblock/comment-only. Include seams reordered topologically (L26-L39); every pin listed in §3. | The plan's own "runtime fatal otherwise" standard now applies uniformly; no downgrade of the invariant needed. |
| OBJ-1 (same finding, Medium) | **FOLDED** — subsumed by O1. | Same evidence, same fix. |
| OBJ-2 (configure: key before route) | **FOLDED** — `configure: bioland.settings` verified present in info.yml; key moved to L8. | One-line accretion; heads L1-L7 no longer declare a link to a nonexistent route. |
| OBJ-3 (requirements enforce linkit 29 seams early) | **FOLDED** — `$contrib_modules` verified as fontawesome+linkit only; array accretes (fontawesome L1, linkit L34). Resolves old OQ4. | Keeps every head installable with only the contribs it uses. |
| OBJ-4 (front_end base_route missing at L21-L22) | **FOLDED** — base_route declarations verified (links.task.yml:22,28); RedirectForm (47 LOC, redirect target `front_end.general` same-seam-safe) + parent route/tab moved into L21; L23 slims to HomePageForm. Cost: L21 becomes a declared ~430 seam-level breach (§2). | Validity coupling is mutual (general tab needs parent; parent redirect needs general), so same-seam is the only clean cut. |
| OBJ-5 (form_alter six-way hand-split undisclosed) | **FOLDED** — mandatory diff-against-final acceptance check added to all 12 bioland.module-touching seams (§3). Collapsing L12-L14 rejected: ~1,548 LOC single seam is a worse mandate violation than the risk it removes. | The diff-check makes every intermediate module body mechanically verifiable against final form. |
| OBJ-6 / O6 invariant hole (convergence) | **FOLDED** — "never deploy/updb this head" note mandated in L26-L30 PR bodies; verified 9074 and 9077 both call the converge fn, so under the new order the invariant holds at EVERY head from L31 (a strict improvement over the draft's L32). | Hole is now 5 disclosed heads, doctrinal only. |
| OBJ-7 (views_pre_render "live code") | **REJECTED with evidence** — `git show origin/latest-dev:bioland.module` lines 682-732: body is `// DISABLED - Uncomment to re-enable` followed by the entire VBO-stripping logic inside one `/* ... */` block (closes at 731); preprocess_html likewise (741-762). Both are live no-op hook implementations with fully commented bodies — the draft's description was correct. L25's description now says "live no-op functions with fully commented bodies" for precision, and the 58/30 raw lines remain counted in L25 (O2's arithmetic point stands regardless). | The claimed executable VBO-stripping behavior does not exist at latest-dev. |
| OBJ-8 (phpunit coverage include on missing src/ at L1) | **REJECTED with evidence** — dry-run executed: PHPUnit 9.6.34 (the repo's own vendor/bin/phpunit) with a scratch phpunit.xml.dist whose `<coverage><include><directory>src</directory>` points at a nonexistent dir: `OK (1 test), EXIT=0`, no warning. Structural branch also closed: ci.yml runs `--log-junit` only with no `--coverage-*` flag, and phpunit.xml.dist contains no `<report>` element, so PHPUnit 9.6 never activates collection and the filter is never exercised even with setup-php's default Xdebug present (jest's `--coverage` touches only JS). No insurance file-move needed. | Risk disproven at the parse path and structurally absent at the driver path. |
| OBJ-9 (breach exception self-granted) | **FOLDED** — §2 restructured as an explicit ratification item and promoted to OQ1 (first decision, blocks ticket-cutting), listing per-file split options taken/declined. | Procedural objection; the user, not the plan, now owns the override. |
| O2 (module apportionment / L25 over ceiling) | **FOLDED** — boundaries re-measured from function map (§4 reconciles to 763 exactly): L25 was ~407; lolspeak-hiding (lines 65-97, core-API-only, no forward deps) moved to L11 → L25 ~375; L5 restated 105 (was 80), L20 ~12 (was 30), L30 hook 22 (was 40), L11 restated ~362. | All four corroborating measurements confirmed. |
| O3 (schema undercount L17/L22) | **FOLDED** — mapping boundaries measured (help_comments 91-163 ≈ 70; mega_menu 193-299 ≈ 107); L17 restated ~668, L22 restated ~640 (also absorbs O3's +47 and OBJ-4's ripple). | Both already breach-marked; sizes now honest. |
| O4 (content_types splittable) | **FOLDED — split taken**: L35 (~305: normalize + configure_content_types + 9029/9072) / L36 (~395: four site-config helpers + 9028/9035/9039/9041), boundaries per the measured internal call graph (sole intra-file coupling normalize↔configure_content_types stays in L35; menu pin moves to L36). Policy for editor.inc/users.inc decided explicitly: ship whole, rationale in §2, ratified via OQ1. | Both halves under ceiling; accretion of an include is already the plan's own model. |
| O5 (search.inc splittable / wrong rationale) | **FOLDED — rationale corrected, ship-whole kept**: L30's justification is now "frozen superseded v1 replay code, deliberately shipped whole" (file header lines 10-17 verified: SUPERSEDED, do-not-wire warning), which the objection itself names as the honest, likely-ratified option; the 3-way split (verified feasible: internal call graph shows only `_final_reindex`→`_ensure_tables`+`_clear_pending`; lifecycle helpers have zero live external callers — bioland.install references are commented out) is documented in §2 and offered in OQ1. | Splitting frozen replay code across 3 PRs adds review surface without adding safety; user ratifies via OQ1. |
| O6 (L8 over ceiling, unowned) | **FOLDED** — the draft's own shave taken: General-tab schema/settings keys deferred to L10; L8 ≈ 400 (largest file 386). Old OQ5 deleted. | No longer an open question in the ratified plan. |
| O7 (ADR 0004 double-assigned) | **FOLDED** — pinned to L31 (search.v2, the convergence path it documents); alternative deleted from L30's row. Coverage claim "exactly one owner" now holds for every file. | Travel-only, zero LOC impact. |

## 9. Open questions (tip chain)

1. Confirm whether the five open PRs already have BL tickets, and whether `mega-menu-2026-08-05` or `latest-dev` proper should be the canonical sync point going forward.
2. ~~Timing of the master promotion PR~~ — RESOLVED 2026-08-26: no promotion PR; the L-series (§7) carries that content and lands first.
3. Whether staging processes require the hook-bearing X1 to deploy in the same release as C7/C8 (the seeded `component_menu_*` config) — if staging deploys per-PR, X1's hook is the only update-path event and must be release-noted.
