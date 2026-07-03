<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for Drupal Module Bioland settings section forms.
 */
abstract class BiolandSettingsFormBase extends ConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation batch service.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationBatchService
   */
  protected $translationBatchService;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
  * Constructs a new Drupal Module Bioland settings form.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bioland\Service\BiolandTranslationBatchService $translation_batch_service
   *   The translation batch service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, BiolandTranslationBatchService $translation_batch_service, Connection $database, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->translationBatchService = $translation_batch_service;
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('bioland.translation_batch'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bioland.settings'];
  }

  /**
   * Gets the branding name based on the is_biosafety_land setting.
   *
   * @return string
   *   Returns 'Biosafety Land' or 'Bioland' based on configuration.
   */
  protected function getBrandingName() {
    $config = $this->config('bioland.settings');
    return $config->get('is_biosafety_land') ? $this->t('Biosafety Land') : $this->t('Bioland');
  }

  /**
   * Gets the page title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|array
   *   The page title.
   */
  public function getTitle() {
    $branding = $this->getBrandingName();
    $title = $this->t('@branding Settings', ['@branding' => $branding]);

    return $title;
  }

  /**
   * Returns the settings section handled by this form.
   *
   * @return string
   *   The section machine name.
   */
  abstract protected function getSection(): string;

  /**
   * Builds the section-specific form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Config\Config $config
   *   The bioland.settings configuration object.
   *
   * @return array
   *   The form array with the section elements added.
   */
  abstract protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array;

  /**
   * Saves the section-specific configuration values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Config\Config $config
   *   The bioland.settings configuration object.
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {}

  /**
   * Gets the site languages with the Lolspeak language filtered out.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   The languages, keyed by language code.
   */
  protected function getFilteredLanguages(): array {
    $languages = $this->languageManager->getLanguages();
    // Filter out Lolspeak language.
    $languages = array_filter($languages, function($language) {
      return $language->getId() !== 'en-x-lolspeak';
    });
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bioland.settings');

    // Store the section in the form state for submit handler
    $form_state->set('bioland_section', $this->getSection());

    $form = $this->buildSectionForm($form, $form_state, $config);

    // Attach the settings toggle library
    $form['#attached']['library'][] = 'bioland/settings_toggle';

    // Add cache metadata for proper invalidation
    $form['#cache']['tags'] = ['config:system.site', 'config:bioland.settings'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('bioland.settings');

    $this->submitSectionForm($form, $form_state, $config);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get content type options from the 'tags' taxonomy vocabulary.
   *
   * @return array
   *   Array of content type options keyed by term ID with term name as value.
   */
  protected function getContentTypeOptions() {
    $options = [];
    try {
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'tags',
          'status' => 1,
        ]);

      foreach ($terms as $term) {
        // Load translated version of the term if available
        if ($term->hasTranslation($current_language)) {
          $term = $term->getTranslation($current_language);
        }
        $tid = (int) $term->id();
        $name = $term->label();
        $options[$tid] = $this->t('@name (@tid)', ['@name' => $name, '@tid' => $tid]);
      }

      // Sort by term ID for consistent ordering.
      ksort($options);
    }
    catch (\Exception $e) {
      // Log error and return empty array if taxonomy terms cannot be loaded.
      \Drupal::logger('bioland')->error('Failed to load content type options from tags vocabulary: @message', ['@message' => $e->getMessage()]);
    }

    return $options;
  }

}
