<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Help Comments settings for the Bioland module.
 */
class BiolandHelpCommentsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_help_comments_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'help_comments';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $languages = $this->getFilteredLanguages();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $has_multiple_languages = count($languages) > 1;

    $form['help_comments'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Help Comments Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Enable checkbox in its own box
    $form['help_comments']['enable'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enable'),
      '#collapsible' => FALSE,
    ];

    $form['help_comments']['enable']['enable_help_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Field Help Comments'),
      '#description' => $this->t('Display contextual help comments for fields based on content type.'),
      '#default_value' => $config->get('enable_help_comments') !== FALSE,
    ];

    // Body Field Help Comment
    $form['help_comments']['body_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Body Field Help'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_help_comments"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['help_comments']['body_help']['help_body_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body Field Help Text'),
      '#description' => $this->t('Help text displayed for the body/content field.'),
      '#default_value' => $config->get('help_comments.body_text') ?: $this->t('This will be the main content of your new site content. The summary can be used to display a brief concise description of your content. Further, the summary will be displayed in list and card views of your record on the website. Alternatively, the first few sentences from the main content will be used.'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];

    // Body Help Translations
    if ($has_multiple_languages) {
      $form['help_comments']['body_help']['body_help_translations'] = [
        '#type' => 'details',
        '#title' => $this->t('Translate Body Help Text'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        $form['help_comments']['body_help']['body_help_translations'][$langcode] = [
          '#type' => 'text_format',
          '#title' => $language->getName(),
          '#default_value' => $config->get("help_comments.body_text_translations.{$langcode}") ?: '',
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
        ];
      }
    }

    // Attachments Field Help Comment - Images
    $form['help_comments']['attachments_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Attachments Field Help'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_help_comments"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['help_comments']['attachments_help']['help_attachments_images_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Images Help Text'),
      '#description' => $this->t('Help text for the images section of attachments.'),
      '#default_value' => $config->get('help_comments.attachments_images_text') ?: $this->t('The first image in order of left to right here, will be the main image of your record displayed on the page and in thumbnails in list and card views. All other images will be displayed below the main content.'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];

    // Images Help Translations
    if ($has_multiple_languages) {
      $form['help_comments']['attachments_help']['images_help_translations'] = [
        '#type' => 'details',
        '#title' => $this->t('Translate Images Help Text'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        $form['help_comments']['attachments_help']['images_help_translations'][$langcode] = [
          '#type' => 'text_format',
          '#title' => $language->getName(),
          '#default_value' => $config->get("help_comments.attachments_images_translations.{$langcode}") ?: '',
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
        ];
      }
    }

    $form['help_comments']['attachments_help']['help_attachments_heroes_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Heroes Help Text'),
      '#description' => $this->t('Help text for the hero banners section.'),
      '#default_value' => $config->get('help_comments.attachments_heroes_text') ?: $this->t('Any page/content type can have multiple hero banners. If there is more than one they will be rotated on an hourly basis.'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];

    // Heroes Help Translations
    if ($has_multiple_languages) {
      $form['help_comments']['attachments_help']['heroes_help_translations'] = [
        '#type' => 'details',
        '#title' => $this->t('Translate Heroes Help Text'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        $form['help_comments']['attachments_help']['heroes_help_translations'][$langcode] = [
          '#type' => 'text_format',
          '#title' => $language->getName(),
          '#default_value' => $config->get("help_comments.attachments_heroes_translations.{$langcode}") ?: '',
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
        ];
      }
    }

    // Promotion Options Field Help Comment
    $form['help_comments']['promotion_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Promotion Options Help'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_help_comments"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $default_promotion_text = $this->t('<b>Promoted to front page:</b>
<ul>
<li>Content appears in featured listings, homepage blocks, or "promoted" views</li>
<li>Useful for highlighting important or timely content</li>
<li>Multiple items can be promoted simultaneously</li>
</ul>

<b>Sticky at top of lists:</b>
<ul>
<li>Content stays pinned at the top of content listings</li>
<li>Remains at top regardless of publication date</li>
<li>Multiple sticky items appear before non-sticky content</li>
</ul>

<b>Using both:</b> Content can be both promoted AND sticky for maximum visibility.');

    $form['help_comments']['promotion_help']['help_promotion_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Promotion Options Help Text'),
      '#description' => $this->t('Help text explaining the Promoted and Sticky options.'),
      '#default_value' => $config->get('help_comments.promotion_text') ?: $default_promotion_text,
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];

    // Promotion Help Translations
    if ($has_multiple_languages) {
      $form['help_comments']['promotion_help']['promotion_help_translations'] = [
        '#type' => 'details',
        '#title' => $this->t('Translate Promotion Help Text'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        $form['help_comments']['promotion_help']['promotion_help_translations'][$langcode] = [
          '#type' => 'text_format',
          '#title' => $language->getName(),
          '#default_value' => $config->get("help_comments.promotion_translations.{$langcode}") ?: '',
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
        ];
      }
    }

    // Order Override Field Help Comment
    $form['help_comments']['order_override_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Order Override Help'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_help_comments"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $default_order_override_text = $this->t('<b>Content sorting priority (highest to lowest):</b>
<ol>
<li><b>Sticky</b> – Items marked "Sticky at top of lists" always appear first followed by:</li>
<li><b>Promoted</b> – Items marked "Promoted to front page" but sort above the other fields, followed by</li>
<li><b>Order Override</b> – A tool for fine grain ordering. Lower numbers appear before higher numbers (e.g., 10 appears before 20).</li>
<li><b>Start Date</b> – Then sorted by start date if it exists, followed by</li>
<li><b>Published Date</b> – Then sorted by publish date if it exists, followed by</li>
<li><b>Last Modified</b> – Finally sorted by most recently updated</li>
</ol>
<b>Tip:</b> Leave it at 10000 which is off essentially, to use default sorting. Use increments of 10 (e.g., 10, 20, 30) to leave room for inserting items later.');

    $form['help_comments']['order_override_help']['help_order_override_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Order Override Help Text'),
      '#description' => $this->t('Help text explaining the Order Override field and content sorting priority.'),
      '#default_value' => $config->get('help_comments.order_override_text') ?: $default_order_override_text,
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
    ];

    // Order Override Help Translations
    if ($has_multiple_languages) {
      $form['help_comments']['order_override_help']['order_override_help_translations'] = [
        '#type' => 'details',
        '#title' => $this->t('Translate Order Override Help Text'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        $form['help_comments']['order_override_help']['order_override_help_translations'][$langcode] = [
          '#type' => 'text_format',
          '#title' => $language->getName(),
          '#default_value' => $config->get("help_comments.order_override_translations.{$langcode}") ?: '',
          '#format' => 'full_html',
          '#allowed_formats' => ['full_html'],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();
    $languages = $this->languageManager->getLanguages();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    // Helper function to extract value and format from text_format fields
    $extractTextFormat = function($field) {
      if (is_array($field)) {
        return [
          'value' => $field['value'] ?? '',
          'format' => $field['format'] ?? 'basic_html',
        ];
      }
      return ['value' => $field, 'format' => 'basic_html'];
    };

    // Extract values and formats for all help text fields
    $body_text = $extractTextFormat($values['help_body_text']);
    $images_text = $extractTextFormat($values['help_attachments_images_text']);
    $heroes_text = $extractTextFormat($values['help_attachments_heroes_text']);
    $promotion_text = $extractTextFormat($values['help_promotion_text']);
    $order_override_text = $extractTextFormat($values['help_order_override_text']);

    $config
      ->set('enable_help_comments', $values['enable_help_comments'])
      ->set('help_comments.body_text', $body_text['value'])
      ->set('help_comments.body_text_format', $body_text['format'])
      ->set('help_comments.attachments_images_text', $images_text['value'])
      ->set('help_comments.attachments_images_text_format', $images_text['format'])
      ->set('help_comments.attachments_heroes_text', $heroes_text['value'])
      ->set('help_comments.attachments_heroes_text_format', $heroes_text['format'])
      ->set('help_comments.promotion_text', $promotion_text['value'])
      ->set('help_comments.promotion_text_format', $promotion_text['format'])
      ->set('help_comments.order_override_text', $order_override_text['value'])
      ->set('help_comments.order_override_text_format', $order_override_text['format']);

    // Save translations for help comments
    if (count($languages) > 1) {
      $body_translations = $values['body_help_translations'] ?? [];
      $images_translations = $values['images_help_translations'] ?? [];
      $heroes_translations = $values['heroes_help_translations'] ?? [];
      $promotion_translations = $values['promotion_help_translations'] ?? [];
      $order_override_translations = $values['order_override_help_translations'] ?? [];

      foreach ($languages as $langcode => $language) {
        if ($langcode === $default_langcode) {
          continue;
        }
        if (!empty($body_translations[$langcode])) {
          $body_trans = $extractTextFormat($body_translations[$langcode]);
          $config->set("help_comments.body_text_translations.{$langcode}", $body_trans['value']);
          $config->set("help_comments.body_text_translations_format.{$langcode}", $body_trans['format']);
        }
        if (!empty($images_translations[$langcode])) {
          $images_trans = $extractTextFormat($images_translations[$langcode]);
          $config->set("help_comments.attachments_images_translations.{$langcode}", $images_trans['value']);
          $config->set("help_comments.attachments_images_translations_format.{$langcode}", $images_trans['format']);
        }
        if (!empty($heroes_translations[$langcode])) {
          $heroes_trans = $extractTextFormat($heroes_translations[$langcode]);
          $config->set("help_comments.attachments_heroes_translations.{$langcode}", $heroes_trans['value']);
          $config->set("help_comments.attachments_heroes_translations_format.{$langcode}", $heroes_trans['format']);
        }
        if (!empty($promotion_translations[$langcode])) {
          $promotion_trans = $extractTextFormat($promotion_translations[$langcode]);
          $config->set("help_comments.promotion_translations.{$langcode}", $promotion_trans['value']);
          $config->set("help_comments.promotion_translations_format.{$langcode}", $promotion_trans['format']);
        }
        if (!empty($order_override_translations[$langcode])) {
          $order_override_trans = $extractTextFormat($order_override_translations[$langcode]);
          $config->set("help_comments.order_override_translations.{$langcode}", $order_override_trans['value']);
          $config->set("help_comments.order_override_translations_format.{$langcode}", $order_override_trans['format']);
        }
      }
    }
  }

}
