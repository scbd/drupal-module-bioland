<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;

/**
 * Configure Bioland home page hero settings.
 */
class BiolandHomePageForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_home_page_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end_home_page';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    // Load heroes and display each in its own fieldset
    $this->buildHeroSections($form);
    return $form;
  }

  /**
   * Builds the home-page hero help/intro markup for the current site flavor.
   *
   * The copy differs by site flavor. Biosafety Land (BSL) sites - detected via
   * the persisted bioland.settings.is_biosafety_land flag (the same flag
   * getBrandingName() reads; it is written by BiolandDmsmConfigService when the
   * DMSM multiSiteCode is 'bsl') - show a single-hero variant sourced from the
   * home_hero_help_bsl_heading / home_hero_help_bsl_text config properties. All
   * other (BL2) sites keep the original rotating-hero copy.
   *
   * Like getBrandingName(), the source strings are wrapped in $this->t() so the
   * translations/bioland.<langcode>.po catalogs apply. The config properties
   * hold the canonical English (source) strings that are used verbatim as the
   * t() msgid; a matching msgid/msgstr pair is shipped in every .po file.
   *
   * Caveat: editing the home_hero_help_bsl_* config replaces the t() source
   * string, so an edited value has no matching .po msgid and renders untranslated
   * for every locale. The BSL branch output is also run through Xss::filterAdmin()
   * as defense-in-depth, since the config-sourced strings become dynamic t()
   * msgids concatenated into raw markup.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The bioland.settings configuration.
   *
   * @return string
   *   The rendered HTML markup for the hero description block.
   */
  protected function buildHeroDescriptionMarkup($config) {
    if ($config->get('is_biosafety_land')) {
      // Biosafety Land variant. Config holds the canonical source strings.
      // Use ?? (not ?:) so only an unset/NULL key falls back to the seeded
      // default: the fallback guards existing sites where the update hook has
      // not yet written the key, not an admin who intentionally blanks the copy.
      $heading = $config->get('help_comments.home_hero_help_bsl_heading')
        ?? 'About Home Page Heroe';
      $body = $config->get('help_comments.home_hero_help_bsl_text')
        ?? 'Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.';

      // Defense-in-depth: the config-sourced strings become dynamic t() msgids
      // concatenated into raw markup, so filter the assembled output through
      // Xss::filterAdmin() before it reaches the #value render array.
      return Xss::filterAdmin(
        '<p style="margin: 0 0 10px 0;"><strong>' . $this->t($heading) . '</strong></p>' .
        '<p style="margin: 0;">' . $this->t($body) . '</p>'
      );
    }

    // Default (BL2) variant - unchanged original copy.
    return '<p style="margin: 0 0 10px 0;"><strong>' . $this->t('About Home Page Heroes') . '</strong></p>' .
      '<p style="margin: 0 0 10px 0;">' . $this->t('Heroes are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you configure the hero banners.') . '</p>' .
      '<p style="margin: 0 0 10px 0;">' . $this->t('The heroes rotate automatically every hour, allowing you to display different messages and images throughout the day.') . '</p>' .
      '<p style="margin: 0;">' . $this->t('If you prefer to display only one hero, simply unpublish the other hero(es) using the Edit button below.') . '</p>';
  }

  /**
   * Build hero sections for the Home Hero(s) tab.
   *
   * @param array &$form
   *   The form array to add hero sections to.
   */
  protected function buildHeroSections(array &$form) {
    try {
      // Query field_attachments_target_id from taxonomy_term__field_attachments
      // where bundle = 'system_pages' and entity_id = 20
      $query = $this->database->select('taxonomy_term__field_attachments', 'fa')
        ->fields('fa', ['field_attachments_target_id'])
        ->condition('fa.bundle', 'system_pages')
        ->condition('fa.entity_id', 20);
      $results = $query->execute()->fetchCol();

      if (empty($results)) {
        $form['no_heroes'] = [
          '#markup' => '<p>' . $this->t('No attachments found for taxonomy term 20.') . '</p>',
        ];
        return;
      }

      // Create outer wrapper fieldset
      $form['home_page_heroes'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Home Page Heros'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#attributes' => ['class' => ['bioland-heroes-wrapper']],
      ];

      // Add description explaining how heroes work. The intro copy differs by
      // site flavor: Biosafety Land (BSL) sites show a single-hero variant,
      // all other (BL2) sites keep the original rotating-hero copy.
      $form['home_page_heroes']['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['bioland-heroes-description'], 'style' => 'margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa; border-radius: 4px;'],
        '#value' => $this->buildHeroDescriptionMarkup($this->config('bioland.settings')),
      ];

      // Add global styles
      $form['home_page_heroes']['hero_styles'] = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .bioland-heroes-wrapper {
            background: #f5f5f5;
            padding: 15px;
          }
          .bioland-hero-content {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 15px;
            background: #e8e8e8;
            border-radius: 4px;
          }
          .bioland-hero-image {
            flex: 0 0 200px;
          }
          .bioland-hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
          }
          .bioland-hero-status {
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
          }
          .bioland-hero-status.published {
            background: #d4edda;
            color: #155724;
          }
          .bioland-hero-status.unpublished {
            background: #f8d7da;
            color: #721c24;
          }
          .bioland-hero-description {
            flex: 1;
            padding: 0 15px;
          }
          .bioland-hero-actions {
            flex: 0 0 auto;
            margin-left: auto;
          }
          .bioland-hero-edit-btn {
            padding: 8px 16px;
            background: #0073aa;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
          }
          .bioland-hero-edit-btn:hover {
            background: #005177;
            color: white !important;
          }
        ',
      ];

      // Load each media entity by ID
      $media_storage = $this->entityTypeManager->getStorage('media');
      $hero_index = 0;

      foreach ($results as $target_id) {
        $entity = $media_storage->load($target_id);
        if (!$entity) {
          continue;
        }

        $hero_index++;
        $title = $entity->label() ?: $this->t('Hero @num', ['@num' => $hero_index]);

        // Create a fieldset for each hero with its title inside the wrapper
        $form['home_page_heroes']['hero_' . $target_id] = [
          '#type' => 'fieldset',
          '#title' => $title,
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        ];

        // Build the content markup
        $content = '<div class="bioland-hero-content">';

        // Image section
        $content .= '<div class="bioland-hero-image">';
        $image_url = NULL;

        // Try field_media_image first (common for media entities)
        if ($entity->hasField('field_media_image') && !$entity->get('field_media_image')->isEmpty()) {
          $image = $entity->get('field_media_image')->entity;
          if ($image) {
            $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
          }
        }
        // Try field_image as fallback
        elseif ($entity->hasField('field_image') && !$entity->get('field_image')->isEmpty()) {
          $image = $entity->get('field_image')->entity;
          if ($image) {
            $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
          }
        }

        if ($image_url) {
          $content .= '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($title) . '" />';
        }
        else {
          $content .= '<div style="width: 200px; height: 120px; background: #ccc; display: flex; align-items: center; justify-content: center; border-radius: 4px;">No Image</div>';
        }

        // Add published/unpublished status
        $is_published = $entity->isPublished();
        $status_class = $is_published ? 'published' : 'unpublished';
        $status_text = $is_published ? $this->t('Published') : $this->t('Unpublished');
        $content .= '<div class="bioland-hero-status ' . $status_class . '">' . $status_text . '</div>';

        $content .= '</div>';

        // Description section
        $content .= '<div class="bioland-hero-description">';
        $description = '';

        // Try different field names for description/body
        if ($entity->hasField('field_description') && !$entity->get('field_description')->isEmpty()) {
          $description = $entity->get('field_description')->value;
        }
        elseif ($entity->hasField('field_body') && !$entity->get('field_body')->isEmpty()) {
          $description = $entity->get('field_body')->value;
        }
        elseif ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
          $description = $entity->get('body')->value;
        }

        if ($description) {
          $content .= '<div>' . $description . '</div>';
        }
        else {
          $content .= '<p><em>' . $this->t('No description available') . '</em></p>';
        }
        $content .= '</div>';

        // Edit button section
        $content .= '<div class="bioland-hero-actions">';
        $edit_url = $entity->toUrl('edit-form')->toString();
        $content .= '<a href="' . htmlspecialchars($edit_url) . '" class="bioland-hero-edit-btn">' . $this->t('Edit') . '</a>';
        $content .= '</div>';

        $content .= '</div>'; // Close hero-content

        $form['home_page_heroes']['hero_' . $target_id]['content'] = [
          '#type' => 'inline_template',
          '#template' => '{{ content|raw }}',
          '#context' => ['content' => $content],
        ];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to load heroes: @message', [
        '@message' => $e->getMessage(),
      ]);
      $form['error'] = [
        '#markup' => '<p>' . $this->t('An error occurred while loading heroes. Please check the logs.') . '</p>',
      ];
    }
  }

}
