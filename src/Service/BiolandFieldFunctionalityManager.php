<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Manages which field-related functionalities are enabled and JS settings for
 * Drupal Module Bioland.
 */
class BiolandFieldFunctionalityManager {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Returns TRUE if any of the major functionalities are enabled.
   */
  public function isAnyFunctionalityEnabled(): bool {
    $c = $this->configFactory->get('bioland.settings');
    return (
      ($c->get('enable_field_visibility') !== FALSE) ||
      ($c->get('enable_additional_fields') !== FALSE) ||
      ($c->get('enable_auto_summary') !== FALSE) ||
      ($c->get('enable_help_comments') !== FALSE)
    );
  }

  /**
   * Build the drupalSettings payload for frontend behaviors.
   */
  public function getJavaScriptSettings(): array {
    $c = $this->configFactory->get('bioland.settings');

    // Get field visibility content type settings and convert to integer arrays
    $url_content_types = $c->get('field_visibility.url_content_types') ?: [];
    $published_content_types = $c->get('field_visibility.published_content_types') ?: [];
    $date_range_content_types = $c->get('field_visibility.date_range_content_types') ?: [];

    // Filter out unchecked values (0) and convert to integers
    $url_content_types = array_values(array_map('intval', array_filter($url_content_types)));
    $published_content_types = array_values(array_map('intval', array_filter($published_content_types)));
    $date_range_content_types = array_values(array_map('intval', array_filter($date_range_content_types)));

    return [
      'enableFieldVisibility' => $c->get('enable_field_visibility') !== FALSE,
      'enableAdditionalFields' => $c->get('enable_additional_fields') !== FALSE,
      'enableAutoSummary' => $c->get('enable_auto_summary') !== FALSE,
      'enableHelpComments' => $c->get('enable_help_comments') !== FALSE,
      'fieldVisibilityRules' => $c->get('field_visibility_rules') ?: '',
      'urlContentTypes' => $url_content_types,
      'publishedContentTypes' => $published_content_types,
      'dateRangeContentTypes' => $date_range_content_types,
    ];
  }
}
