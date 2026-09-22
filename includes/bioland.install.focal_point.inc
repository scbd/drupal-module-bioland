<?php

/**
 * @file
 * Hero image focal point ("window selector") install functions (BL-841).
 *
 * Focal Point is a soft dependency: drupal/focal_point is suggested in
 * composer.json, not required in bioland.info.yml, so sites that have not
 * composer-required it keep working and are told so by bioland_requirements().
 */

use Drupal\bioland\BiolandFocalPoint;

/**
 * Switches the hero media image widget to Focal Point.
 *
 * Installs focal_point when its code is present, points the
 * media.hero.default form display field_media_image widget at
 * image_focal_point, and makes sure a jsonapi_extras media--hero override (if
 * one exists) does not hide bioland_focal_point. Idempotent.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_hero_focal_point() {
  if (!\Drupal::moduleHandler()->moduleExists('focal_point')) {
    if (!\Drupal::service('extension.list.module')->exists('focal_point')) {
      return (string) t('Focal Point is not available (composer require drupal/focal_point); skipped the hero focal point widget.');
    }
    \Drupal::service('module_installer')->install(['focal_point']);
  }

  $messages = [];
  $display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('media.hero.default');
  $component = $display ? $display->getComponent(BiolandFocalPoint::IMAGE_FIELD) : NULL;

  if (!$component) {
    $messages[] = t('Form display media.hero.default or its @field widget not found; skipped the focal point widget.', ['@field' => BiolandFocalPoint::IMAGE_FIELD]);
  }
  elseif (($component['type'] ?? '') === 'image_focal_point') {
    $messages[] = t('Hero image already uses the focal point widget.');
  }
  else {
    // Keep existing image widget settings; the focal point widget requires a
    // preview image style.
    $component['type'] = 'image_focal_point';
    $component['settings'] = [
      'preview_link' => TRUE,
      'offsets' => '50,50',
    ] + ($component['settings'] ?? []) + [
      'progress_indicator' => 'throbber',
      'preview_image_style' => 'thumbnail',
    ];
    $display->setComponent(BiolandFocalPoint::IMAGE_FIELD, $component)->save();
    $messages[] = t('Hero image now uses the focal point widget.');
  }

  $messages[] = _bioland_expose_hero_focal_point_jsonapi();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Enables bioland_focal_point in a jsonapi_extras media--hero override.
 *
 * Without an override, jsonapi_extras exposes every field (computed ones
 * included) under its own name, so only an existing override is touched.
 *
 * @return string
 *   Status message.
 */
function _bioland_expose_hero_focal_point_jsonapi() {
  if (!\Drupal::moduleHandler()->moduleExists('jsonapi_extras')) {
    return (string) t('jsonapi_extras not enabled; @field is exposed by core JSON:API.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $config = \Drupal::configFactory()->getEditable('jsonapi_extras.jsonapi_resource_config.media--hero');
  if ($config->isNew()) {
    return (string) t('No media--hero JSON:API override; @field is exposed by default.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $fields = $config->get('resourceFields') ?: [];
  $fields[BiolandFocalPoint::FIELD_NAME] = [
    'fieldName' => BiolandFocalPoint::FIELD_NAME,
    'publicName' => BiolandFocalPoint::FIELD_NAME,
    'enhancer' => ['id' => ''],
    'disabled' => FALSE,
  ];
  $config->set('resourceFields', $fields);
  $config->save(TRUE);

  return (string) t('Exposed @field on the media--hero JSON:API resource.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * BL-841: enable the hero image focal point (window selector).
 *
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
 * config. Convergence runs first so a focal point failure cannot skip it.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9084() {
  $messages = [_bioland_v2_update_search_and_facets_config()];
  $messages[] = _bioland_configure_hero_focal_point();
  return implode(' ', array_map('strval', $messages));
}
