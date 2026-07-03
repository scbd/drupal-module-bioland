<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mega Menu settings for the Bioland module.
 */
class BiolandMegaMenuForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_mega_menu_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end_mega_menu';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $form['mega_menu_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mega Menu Component Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];

    // Content Type Menus section
    $form['mega_menu_settings']['content_type_menus'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Type Menus'),
      '#open' => FALSE,
      '#description' => $this->t('Configure automatic menu entries for each content type in the mega menu.'),
      '#tree' => TRUE,
    ];

    // Add rescan button
    // $form['mega_menu_settings']['content_type_menus']['rescan_menus'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Rescan Menus'),
    //   '#submit' => ['::submitRescanMenus'],
    //   '#limit_validation_errors' => [],
    //   '#attributes' => ['class' => ['button', 'button--small']],
    //   '#description' => $this->t('Click to rescan all menus for content type classes.'),
    // ];

    // Get enabled content types with plural field (alphabetically sorted)
    $content_types = $this->getContentTypeOptionsForMegaMenu();

    // Get content type TIDs that are actually in use in menus
    // TODO: Debug why this returns empty - for now show all content types
    // $tids_in_use = $this->getContentTypeTidsInUse();

    // Create a fieldset for each content type
    foreach ($content_types as $tid => $type_name) {
      // TODO: Re-enable this filter after debugging getContentTypeTidsInUse()
      // Only show if this content type is in use in menus
      // if (!in_array($tid, $tids_in_use)) {
      //   continue;
      // }

      $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid] = [
        '#type' => 'details',
        '#title' => $this->t('@type_name Menu', ['@type_name' => $type_name]),
        '#open' => FALSE,
      ];

      // Maximum menus to show (1-20)
      $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['max_menus_' . $tid] = [
        '#type' => 'select',
        '#title' => $this->t('Maximum menus to show'),
        '#options' => array_combine(range(1, 20), range(1, 20)),
        '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.max_menus') ?? 6,
        '#description' => $this->t('Maximum number of menu entries to display for this content type.'),
      ];

      // Drupal menus position (top/bottom)
      $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['menu_position_' . $tid] = [
        '#type' => 'select',
        '#title' => $this->t('Drupal menus position'),
        '#options' => [
          'top' => $this->t('Top'),
          'bottom' => $this->t('Bottom'),
        ],
        '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.menu_position') ?? 'top',
        '#description' => $this->t('Drupal menu entries will appear at the top or bottom of automatic entries.'),
      ];

      // Show menu even if empty checkbox
      $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['show_if_empty_' . $tid] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show menu even if empty'),
        '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.show_if_empty') ?? FALSE,
        '#description' => $this->t('Display this menu section even when there are no entries.'),
      ];
    }

    // Content Types Statistics Menu section
    $form['mega_menu_settings']['content_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Types Statistics Menu'),
      '#open' => FALSE,
    ];

    // Menu position (top/bottom)
    $form['mega_menu_settings']['content_types']['content_types_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $config->get('mega_menu.content_types.position') ?? 'top',
      '#description' => $this->t('Position where menu entries manually created in drupal will appear next to the automated section.'),
    ];

    $form['mega_menu_settings']['content_types']['hide_content_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide content types in mega menu'),
      '#default_value' => $config->get('mega_menu.content_types.hide_content_types') ?? TRUE,
      '#description' => $this->t('When enabled, content types will be hidden in the mega menu.'),
    ];

    // Additional menu sections
    $additional_menus = [
      'country_profiles' => $this->t('Country Profiles Menu'),
      'focal_points' => $this->t('Focal Points Menu'),
      'national_targets' => $this->t('National Targets Menu'),
      'national_report' => $this->t('National Report Menu'),
      'bch' => $this->t('BCH Menu'),
      'absch' => $this->t('ABSCH Menu'),
      'forums' => $this->t('Forums Menu'),
    ];

    foreach ($additional_menus as $menu_key => $menu_title) {
      $form['mega_menu_settings'][$menu_key] = [
        '#type' => 'details',
        '#title' => $menu_title,
        '#open' => FALSE,
      ];

      // Menu position (top/bottom)
      $form['mega_menu_settings'][$menu_key][$menu_key . '_position'] = [
        '#type' => 'select',
        '#title' => $this->t('Menu position'),
        '#options' => [
          'top' => $this->t('Top'),
          'bottom' => $this->t('Bottom'),
        ],
        '#default_value' => $config->get('mega_menu.' . $menu_key . '.position') ?? 'top',
        '#description' => $this->t('Position where menu entries manually created in drupal will appear next to the automated section.'),
      ];

      // Show menu even if empty checkbox
      $form['mega_menu_settings'][$menu_key][$menu_key . '_show_if_empty'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show menu even if empty'),
        '#default_value' => $config->get('mega_menu.' . $menu_key . '.show_if_empty') ?? FALSE,
        '#description' => $this->t('Display this menu section even when there are no entries.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

    // Clear cached visible TIDs to force rescan on next load
    $config->clear('mega_menu.content_type_menus.visible_content_type_menus');

    // Get mega_menu_settings values - with #tree => TRUE, values are nested
    $mega_menu_values = $values['mega_menu_settings'] ?? [];
    $content_type_menus = $mega_menu_values['content_type_menus'] ?? [];

    // Debug: Log only keys to understand structure without memory issues
    \Drupal::logger('bioland')->debug('Mega Menu submit - top-level keys: @keys', [
      '@keys' => implode(', ', array_keys($values)),
    ]);
    \Drupal::logger('bioland')->debug('Mega Menu submit - mega_menu_values keys: @keys', [
      '@keys' => is_array($mega_menu_values) ? implode(', ', array_keys($mega_menu_values)) : 'NOT AN ARRAY',
    ]);
    \Drupal::logger('bioland')->debug('Mega Menu submit - content_type_menus keys: @keys', [
      '@keys' => is_array($content_type_menus) ? implode(', ', array_keys($content_type_menus)) : 'NOT AN ARRAY',
    ]);

    // Save mega menu content type settings
    // Form values are nested: $values['mega_menu_settings']['content_type_menus']['content_type_X']['max_menus_X']
    $content_types = $this->getContentTypeOptionsForMegaMenu();

    foreach ($content_types as $tid => $type_name) {
      $content_type_key = 'content_type_' . $tid;
      $max_menus_key = 'max_menus_' . $tid;
      $menu_position_key = 'menu_position_' . $tid;
      $show_if_empty_key = 'show_if_empty_' . $tid;

      // Values are nested under content_type_menus -> content_type_X
      $content_type_values = $content_type_menus[$content_type_key] ?? [];

      if (isset($content_type_values[$max_menus_key])) {
        $config->set('mega_menu.content_type_menus.' . $tid . '.max_menus', (int) $content_type_values[$max_menus_key]);
      }

      if (isset($content_type_values[$menu_position_key])) {
        $config->set('mega_menu.content_type_menus.' . $tid . '.menu_position', $content_type_values[$menu_position_key]);
      }

      if (isset($content_type_values[$show_if_empty_key])) {
        $config->set('mega_menu.content_type_menus.' . $tid . '.show_if_empty', (bool) $content_type_values[$show_if_empty_key]);
      }
    }

    // Save content types settings (nested under mega_menu_settings -> content_types)
    $content_types_settings = $mega_menu_values['content_types'] ?? [];
    if (isset($content_types_settings['content_types_position'])) {
      $config->set('mega_menu.content_types.position', $content_types_settings['content_types_position']);
    }
    $config->set('mega_menu.content_types.hide_content_types', (bool) ($content_types_settings['hide_content_types'] ?? TRUE));

    // Save additional menu settings
    // Form values are nested: $values['mega_menu_settings']['country_profiles']['country_profiles_position']
    $additional_menus = ['country_profiles', 'focal_points', 'national_targets', 'national_report', 'bch', 'absch', 'forums'];

    foreach ($additional_menus as $menu_key) {
      $position_key = $menu_key . '_position';
      $show_if_empty_key = $menu_key . '_show_if_empty';

      // Values are nested under mega_menu_settings -> menu_key
      $menu_values = $mega_menu_values[$menu_key] ?? [];

      if (isset($menu_values[$position_key])) {
        $config->set('mega_menu.' . $menu_key . '.position', $menu_values[$position_key]);
      }

      if (isset($menu_values[$show_if_empty_key])) {
        $config->set('mega_menu.' . $menu_key . '.show_if_empty', (bool) $menu_values[$show_if_empty_key]);
      }
    }
  }

  /**
   * Submit handler for rescan menus button.
   */
  public function submitRescanMenus(array &$form, FormStateInterface $form_state) {
    // Clear the cached visible TIDs
    $this->configFactory->getEditable('bioland.settings')
      ->clear('mega_menu.content_type_menus.visible_content_type_menus')
      ->save();

    $this->messenger()->addStatus($this->t('Menu scan cache cleared. The page will reload to show updated content types.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Get content type options with plural field for mega menu display.
   *
   * @return array
   *   Array of content type options keyed by term ID with plural field value,
   *   sorted alphabetically by plural name.
   */
  protected function getContentTypeOptionsForMegaMenu() {
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
        // Get plural field if available, fallback to label
        $plural = $term->hasField('field_plural') && !$term->get('field_plural')->isEmpty()
          ? $term->get('field_plural')->value
          : $term->label();
        $options[$tid] = $plural;
      }

      // Sort alphabetically by plural name
      asort($options);
    }
    catch (\Exception $e) {
      // Log error and return empty array if taxonomy terms cannot be loaded.
      \Drupal::logger('bioland')->error('Failed to load content type options for mega menu: @message', ['@message' => $e->getMessage()]);
    }

    return $options;
  }

  /**
   * Get content type reference data.
   *
   * This is a reference of all known content types with their metadata.
   * Can be imported and used for configuration, validation, or frontend display.
   *
   * @return array
   *   Array of content type data, each with:
   *   - tid: Taxonomy term ID
   *   - name: Singular name
   *   - field_plural: Plural name
   *   - icon: Emoji icon
   *   - slug: Kebab-case version of singular name
   */
  protected static function getContentTypeReference() {
    return [
      ['tid' => 2, 'name' => 'News', 'field_plural' => 'News', 'icon' => '📰', 'slug' => 'news'],
      ['tid' => 3, 'name' => 'Event', 'field_plural' => 'Events', 'icon' => '📅', 'slug' => 'event'],
      ['tid' => 4, 'name' => 'Learning Resource', 'field_plural' => 'Learning Resources', 'icon' => '🎓', 'slug' => 'learning-resource'],
      ['tid' => 5, 'name' => 'Project', 'field_plural' => 'Projects', 'icon' => '📊', 'slug' => 'project'],
      ['tid' => 6, 'name' => 'Basic Page', 'field_plural' => 'Basic Pages', 'icon' => '📄', 'slug' => 'basic-page'],
      ['tid' => 54, 'name' => 'Article', 'field_plural' => 'Articles', 'icon' => '📄', 'slug' => 'article'],
      ['tid' => 8, 'name' => 'Government Ministry or Institute', 'field_plural' => 'Government Ministries or Institutes', 'icon' => '🏛️', 'slug' => 'government-ministry-or-institute'],
      ['tid' => 9, 'name' => 'Ecosystem', 'field_plural' => 'Ecosystems', 'icon' => '🌍', 'slug' => 'ecosystem'],
      ['tid' => 10, 'name' => 'Protected Area', 'field_plural' => 'Protected Areas', 'icon' => '🏞️', 'slug' => 'protected-area'],
      ['tid' => 11, 'name' => 'Biodiversity Data', 'field_plural' => 'Biodiversity Data', 'icon' => '📈', 'slug' => 'biodiversity-data'],
      ['tid' => 12, 'name' => 'Document', 'field_plural' => 'Documents', 'icon' => '📋', 'slug' => 'document'],
      ['tid' => 13, 'name' => 'Related Website', 'field_plural' => 'Related Websites', 'icon' => '🔗', 'slug' => 'related-website'],
      ['tid' => 15, 'name' => 'Other', 'field_plural' => 'Others', 'icon' => '⚙️', 'slug' => 'other'],
      ['tid' => 16, 'name' => 'Image or Video', 'field_plural' => 'Images or Videos', 'icon' => '🎬', 'slug' => 'image-or-video'],
      ['tid' => 55, 'name' => 'Other Resource', 'field_plural' => 'Other Resources', 'icon' => '⚙️', 'slug' => 'other-resource'],
      ['tid' => 43, 'name' => 'FAQ', 'field_plural' => 'FAQs', 'icon' => '❓', 'slug' => 'faq'],
      ['tid' => 44, 'name' => 'National Information', 'field_plural' => 'National Informations', 'icon' => '🏴', 'slug' => 'national-information'],
      ['tid' => 45, 'name' => 'Status of LMO', 'field_plural' => 'Status of LMOs', 'icon' => '🧬', 'slug' => 'status-of-lmo'],
      ['tid' => 46, 'name' => 'Field Trial', 'field_plural' => 'Field Trials', 'icon' => '🌱', 'slug' => 'field-trial'],
      ['tid' => 47, 'name' => 'National Mainstreaming Strategy', 'field_plural' => 'National Mainstreaming Strategies', 'icon' => '🗂️', 'slug' => 'national-mainstreaming-strategy'],
      ['tid' => 48, 'name' => 'Capacity Building', 'field_plural' => 'Capacity Building', 'icon' => '🔧', 'slug' => 'capacity-building'],
      ['tid' => 49, 'name' => 'Announcement', 'field_plural' => 'Announcements', 'icon' => '📢', 'slug' => 'announcement'],
      ['tid' => 50, 'name' => 'Contact', 'field_plural' => 'Contacts', 'icon' => '📞', 'slug' => 'contact'],
    ];
  }

  /**
   * Get content type TIDs that are in use in menus.
   *
   * Scans all menus (except excluded ones) for bl2-content-type-* classes
   * and returns the TIDs of content types that are referenced.
   *
   * @return array
   *   Array of taxonomy term IDs that are in use in menus.
   */
  protected function getContentTypeTidsInUse() {
    $config = $this->config('bioland.settings');
    $enable_debug = $config->get('enable_debug_logging') ?: FALSE;

    // Check if we already have saved visible content type menus
    // $saved_tids = $config->get('mega_menu.content_type_menus.visible_content_type_menus');
    // if (!empty($saved_tids) && is_array($saved_tids)) {
    //   if ($enable_debug) {
    //     \Drupal::logger('bioland')->debug('Mega Menu: Using saved visible content type TIDs: @tids', [
    //       '@tids' => implode(', ', $saved_tids),
    //     ]);
    //   }
    //   return $saved_tids;
    // }

    $tids_in_use = [];
    $excluded_menus = ['admin', 'footer', 'account', 'tools', 'footer-credits', 'main'];

    // Get content type reference for slug-to-tid mapping
    $content_type_ref = self::getContentTypeReference();
    $slug_to_tid = [];
    foreach ($content_type_ref as $ct) {
      $slug_to_tid[$ct['slug']] = $ct['tid'];
    }

    try {
      // Load all menus
      $menu_storage = $this->entityTypeManager->getStorage('menu');
      $menus = $menu_storage->loadMultiple();

      if ($enable_debug) {
        \Drupal::logger('bioland')->debug('Mega Menu: Scanning menus for content type classes');
      }

      foreach ($menus as $menu) {
        $menu_id = $menu->id();

        // Skip excluded menus
        if (in_array($menu_id, $excluded_menus)) {
          continue;
        }

        // Load ALL menu items from database for this menu to scan all levels
        $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
        $menu_links = $menu_link_storage->loadByProperties([
          'menu_name' => $menu_id,
          'enabled' => 1,
        ]);

       // if ($enable_debug) {
          \Drupal::logger('bioland')->debug('Mega Menu: Scanning menu @menu with @count links', [
            '@menu' => $menu_id,
            '@count' => count($menu_links),
          ]);
      //  }

        // Process each menu item
        foreach ($menu_links as $menu_link) {
          // Get link options which contain attributes
          $link_field = $menu_link->get('link');
          if ($link_field->isEmpty()) {
            continue;
          }

          $link_item = $link_field->first();
          if (!$link_item) {
            continue;
          }


          // Get options - it may be stored as 'options' property or in the value array
          $options = [];
          $link_value = $link_item->getValue();

           \Drupal::logger('bioland')->debug('Mega Menu link @menu', [
                '@menu' => print_r($link_value, TRUE)
          ]);
          if (isset($link_value['options'])) {
            $options = $link_value['options'];
          }
          \Drupal::logger('bioland')->debug('Mega Menu options: @menu', [
                '@menu' => print_r($link_value['options'], TRUE)
          ]);
          // Check for classes in attributes
          if (isset($options['attributes']['class'])) {
            $classes = is_array($options['attributes']['class'])
              ? $options['attributes']['class']
              : explode(' ', $options['attributes']['class']);

            if ($enable_debug && !empty($classes)) {
              \Drupal::logger('bioland')->debug('Mega Menu: Found classes in menu @menu item "@title": @classes', [
                '@menu' => $menu_id,
                '@title' => $menu_link->label(),
                '@classes' => implode(', ', $classes),
              ]);
            }

                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: classes raw @class', [
                    '@class' => implode(', ', $classes)
                    ]);
                  }
            // Extract bl2-content-type-* classes
            foreach ($classes as $class) {


              if (strpos($class, 'bl2-content-type-') === 0) {
                // Remove prefix to get slug
                $slug = substr($class, strlen('bl2-content-type-'));

                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: class @class', [
                    '@clas' => $class
                    ]);
                  }
                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: Found bl2-content-type class: @class (slug: @slug) in item "@title"', [
                    '@class' => $class,
                    '@slug' => $slug,
                    '@title' => $menu_link->label(),
                  ]);
                }

                // Match to TID
                if (isset($slug_to_tid[$slug])) {
                  $tids_in_use[] = $slug_to_tid[$slug];
                  if ($enable_debug) {
                    \Drupal::logger('bioland')->debug('Mega Menu: Matched slug @slug to TID @tid', [
                      '@slug' => $slug,
                      '@tid' => $slug_to_tid[$slug],
                    ]);
                  }
                } else {
                  if ($enable_debug) {
                    \Drupal::logger('bioland')->debug('Mega Menu: No TID match found for slug: @slug', [
                      '@slug' => $slug,
                    ]);
                  }
                }
              }
            }
          }
        }
      }

      // Return unique TIDs
      $unique_tids = array_unique($tids_in_use);

      if ($enable_debug) {
        \Drupal::logger('bioland')->debug('Mega Menu: Final visible content type TIDs: @tids', [
          '@tids' => implode(', ', $unique_tids),
        ]);
      }

      // Save the visible TIDs to config for future use
      \Drupal::configFactory()->getEditable('bioland.settings')
        ->set('mega_menu.content_type_menus.visible_content_type_menus', array_values($unique_tids))
        ->save();

      return $unique_tids;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to scan menus for content types: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
