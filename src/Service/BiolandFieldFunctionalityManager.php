<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

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
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ?LanguageManagerInterface $language_manager = NULL) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
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

    // Build help comments settings with translation support
    $helpComments = [];
    if ($this->languageManager) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

      $helpComments = [
        'bodyText' => $this->getTranslatedHelpText($c, 'help_comments.body_text', 'help_comments.body_text_translations', $langcode, $default_langcode),
        'attachmentsImagesText' => $this->getTranslatedHelpText($c, 'help_comments.attachments_images_text', 'help_comments.attachments_images_translations', $langcode, $default_langcode),
        'attachmentsHeroesText' => $this->getTranslatedHelpText($c, 'help_comments.attachments_heroes_text', 'help_comments.attachments_heroes_translations', $langcode, $default_langcode),
        'promotionText' => $this->getTranslatedHelpText($c, 'help_comments.promotion_text', 'help_comments.promotion_translations', $langcode, $default_langcode),
        'orderOverrideText' => $this->getTranslatedHelpText($c, 'help_comments.order_override_text', 'help_comments.order_override_translations', $langcode, $default_langcode),
      ];
    }

    // Build debug logging settings
    $enableDebugLogging = $c->get('enable_debug_logging') ?: FALSE;
    $debugLogAreas = [
      'fieldVisibility' => $enableDebugLogging && ($c->get('debug_log_areas.field_visibility') !== FALSE),
      'additionalFields' => $enableDebugLogging && ($c->get('debug_log_areas.additional_fields') !== FALSE),
      'autoSummary' => $enableDebugLogging && ($c->get('debug_log_areas.auto_summary') !== FALSE),
      'helpComments' => $enableDebugLogging && ($c->get('debug_log_areas.help_comments') !== FALSE),
    ];

    // Get additional tags content type settings, falling back to the fixed
    // defaults, then filter out unchecked values (0) and convert to integers.
    $additional_tags = [];
    foreach (BiolandAdditionalTagDefaults::CONTENT_TYPES as $key => $default) {
      $content_types = $c->get('additional_tags.' . $key) ?: $default;
      $additional_tags[BiolandAdditionalTagDefaults::JS_KEYS[$key]] = array_values(array_map('intval', array_filter($content_types)));
    }

    return [
      'enableFieldVisibility' => $c->get('enable_field_visibility') !== FALSE,
      'enableAdditionalFields' => $c->get('enable_additional_fields') !== FALSE,
      'enableAutoSummary' => $c->get('enable_auto_summary') !== FALSE,
      'enableHelpComments' => $c->get('enable_help_comments') !== FALSE,
      'fieldVisibilityRules' => $c->get('field_visibility_rules') ?: '',
      'urlContentTypes' => $url_content_types,
      'publishedContentTypes' => $published_content_types,
      'dateRangeContentTypes' => $date_range_content_types,
      'additionalTags' => $additional_tags,
      'helpComments' => $helpComments,
      'enableDebugLogging' => $enableDebugLogging,
      'debugLogAreas' => $debugLogAreas,
    ];
  }

  /**
   * Get translated help text, falling back to default if no translation exists.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   * @param string $default_key
   *   The config key for the default text.
   * @param string $translations_key
   *   The config key for translations mapping.
   * @param string $langcode
   *   The current language code.
   * @param string $default_langcode
   *   The default language code.
   *
   * @return string
   *   The translated text or default.
   */
  protected function getTranslatedHelpText($config, string $default_key, string $translations_key, string $langcode, string $default_langcode): string {
    $default = $config->get($default_key) ?: '';

    // If current language is default, return default text
    if ($langcode === $default_langcode) {
      return $default;
    }

    // Check for translation
    $translation = $config->get("{$translations_key}.{$langcode}");
    if (!empty($translation)) {
      return $translation;
    }

    return $default;
  }
}
