<?php

namespace Drupal\bioland\Service;

/**
 * Provides the fixed content type mappings for the additional tag groups.
 *
 * Each additional tag group is shown on exactly one content type (a term in
 * the 'tags' vocabulary), and the pairing is identical on every Bioland site,
 * so it is not site-configurable. The values live here rather than on a
 * consumer so that the writers (BiolandTagsForm, bioland_update_9077) and the
 * reader (BiolandFieldFunctionalityManager) all depend on the same table.
 *
 * These values are also carried in config/install/bioland.settings.yml for
 * fresh installs; BiolandAdditionalTagDefaultsTest pins the two in sync.
 */
class BiolandAdditionalTagDefaults {

  /**
   * The fixed content type (tags vocabulary term ID) mapping per tag group.
   *
   * Keyed by the bioland.settings key under 'additional_tags'.
   *
   * @var array<string, int[]>
   */
  const CONTENT_TYPES = [
    'event_status_content_types' => [3],
    'project_status_content_types' => [5],
    'organization_types_content_types' => [8],
    'ecosystem_types_content_types' => [9],
    'document_types_content_types' => [12],
  ];

  /**
   * Maps each mapping key to its drupalSettings (camelCase) counterpart.
   *
   * @var array<string, string>
   */
  const JS_KEYS = [
    'event_status_content_types' => 'eventStatusContentTypes',
    'project_status_content_types' => 'projectStatusContentTypes',
    'organization_types_content_types' => 'organizationTypesContentTypes',
    'ecosystem_types_content_types' => 'ecosystemTypesContentTypes',
    'document_types_content_types' => 'documentTypesContentTypes',
  ];

}
