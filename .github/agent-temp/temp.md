editor.editor.full_html
      'linkit_extension' => 
      array (
        'linkit_enabled' => true,
        'linkit_profile' => 'default',
      ),

filter.format.full_html
    'linkit' => 
    array (
      'id' => 'linkit',
      'provider' => 'linkit',
      'status' => true,
      'weight' => 0,
      'settings' => 
      array (
        'title' => true,
        'media_substitution' => 'metadata',
      ),
    ),

linkit.linkit_profile.default
    array (
  'uuid' => '2ecf31ca-ba4c-46c6-bcc5-8ec3b3e83fd3',
  'langcode' => 'en',
  'status' => true,
  'dependencies' =>
  array (
    'module' =>
    array (
      0 => 'node',
      1 => 'taxonomy',
    ),
  ),
  '_core' =>
  array (
    'default_config_hash' => '0Jw_BFJCCtWk187tIYvME58VFpYwPaAdrc4eRtAyHH0',
  ),
  'label' => 'Default',
  'id' => 'default',
  'description' => 'A default Linkit profile',
  'matchers' =>
  array (
    '556010a3-e317-48b3-b4ed-854c10f4b950' =>
    array (
      'id' => 'entity:node',
      'uuid' => '556010a3-e317-48b3-b4ed-854c10f4b950',
      'settings' =>
      array (
        'metadata' => '[language:prefix][node:url:path]',
        'bundles' =>
        array (
          'content' => 'content',
        ),
        'group_by_bundle' => true,
        'substitution_type' => 'canonical',
        'limit' => 20,
        'include_unpublished' => true,
      ),
      'weight' => -10,
    ),
    '33b36486-f1e5-46b3-86dc-e2a30e72030a' =>
    array (
      'id' => 'entity:taxonomy_term',
      'uuid' => '33b36486-f1e5-46b3-86dc-e2a30e72030a',
      'settings' =>
      array (
        'metadata' => '[language:prefix][term:url:path]',
        'bundles' =>
        array (
          'system_pages' => 'system_pages',
          'tags' => 'tags',
        ),
        'group_by_bundle' => true,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ),
      'weight' => -9,
    ),
    'bb3ad7f0-3d71-41b6-8a3c-36a1219c86d5' =>
    array (
      'id' => 'entity:media',
      'uuid' => 'bb3ad7f0-3d71-41b6-8a3c-36a1219c86d5',
      'settings' =>
      array (
        'metadata' => '[language:prefix][media:url:path]',
        'bundles' =>
        array (
          'document' => 'document',
          'image' => 'image',
          'remote_video' => 'remote_video',
        ),
        'group_by_bundle' => true,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ),
      'weight' => -8,
    ),
    'ef16248e-964b-4932-a8d6-1b7723c0fb57' =>
    array (
      'id' => 'external',
      'uuid' => 'ef16248e-964b-4932-a8d6-1b7723c0fb57',
      'settings' =>
      array (
      ),
      'weight' => -7,
    ),
    '8d77bb1b-e3f4-4555-bce3-e48fc83f1c11' =>
    array (
      'id' => 'email',
      'uuid' => '8d77bb1b-e3f4-4555-bce3-e48fc83f1c11',
      'settings' =>
      array (
      ),
      'weight' => -6,
    ),
    '52c06754-70e8-4940-8247-afcf7eea44fe' =>
    array (
      'id' => 'entity:comment',
      'uuid' => '52c06754-70e8-4940-8247-afcf7eea44fe',
      'settings' =>
      array (
        'metadata' => '[language:prefix][comment:url:path]',
        'bundles' =>
        array (
          'comment' => 'comment',
          'comment_media' => 'comment_media',
          'comment_taxonomy' => 'comment_taxonomy',
        ),
        'group_by_bundle' => false,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ),
      'weight' => 0,
    ),
  ),
)
# Drupal Field Configuration

| Field name | Entity type | Field type | Used in | Summary |
|------------|-------------|------------|---------|---------|
| comment_body | comment | Text (formatted, long) | Comment_content, Comment_forum, Comment_media, Comment_taxonomy | |
| field_description | media | Text (formatted, long) | Remote video, Document, Image, Hero | |
| body | node | Text (formatted, long, with summary) | Site Content, Forum topic | |


can you include



on next update or install can you set the field settings for body, field_description, comment_body for all entity types to have the only format option available 'full_html'.

then run update hook to set existing content to use full_html format for all the above comments, media and nodes.


# Drupal Field Configuration

| Field name | Entity type | Field type | Used in | Summary |
|------------|-------------|------------|---------|---------|
| comment_body | comment | Text (formatted, long) | Comment_content, Comment_forum, Comment_media, Comment_taxonomy | |
| field_i18n | comment | Boolean | Comment_forum, Comment_content, Comment_media, Comment_taxonomy | |
| field_comments | media | Comments | Image, Remote video, Document | |
| field_credits | media | Text (plain) | Hero, Image | |
| field_description | media | Text (formatted, long) | Remote video, Document, Image, Hero | |
| field_hash_unique | media | Text (plain) | Document, Image | |
| field_height | media | Text (plain) | Image, Hero | |
| field_i18n | media | Boolean | Document, Hero, Image, Remote video | |
| field_media_document | media | File | Document | |
| field_media_image | media | Image | Image, Hero, Document, Remote video | |
| field_media_oembed_video | media | Text (plain) | Remote video | |
| field_mime | media | Text (plain) | Image, Document, Hero | |
| field_phash | media | Text (plain) | Image | |
| field_size | media | Text (plain) | Image, Document, Hero | |
| field_width | media | Text (plain) | Image, Hero | |
| body | node | Text (formatted, long, with summary) | Site Content, Forum topic | |
| comment | node | Comments | Site Content | |
| comment_forum | node | Comments | Forum topic | |
| field_attachments | node | Entity reference | Site Content | Reference type: Media |
| field_components | node | Entity reference | Site Content | Reference type: Taxonomy term |
| field_end_date | node | Date | Site Content | |
| field_i18n | node | Boolean | Site Content, Forum topic | |
| field_migrated | node | Text (plain, long) | Site Content | |
| field_order | node | Number (integer) | Site Content | |
| field_published | node | Date | Site Content | |
| field_related | node | Entity reference | Site Content | Reference type: Content |
| field_start_date | node | Date | Site Content | |
| field_tags | node | SCBD Thesaurus | Site Content | |
| field_type_placement | node | Entity reference | Site Content | Reference type: Taxonomy term |
| field_url | node | Link | Site Content | |
| taxonomy_forums | node | Entity reference | Forum topic | Reference type: Taxonomy term |
| field_attachments | taxonomy_term | Entity reference | System Pages | Reference type: Media |
| field_color | taxonomy_term | Text (plain) | | |
| field_comments | taxonomy_term | Comments | System Pages | |
| field_components | taxonomy_term | Entity reference | System Pages, Content Types | Reference type: Taxonomy term |
| field_i18n | taxonomy_term | Boolean | Content Types, System Pages, Forums | |
| field_plural | taxonomy_term | Text (plain) | Content Types | |
| field_search | taxonomy_term | Entity reference | System Pages | Reference type: Taxonomy term |
| forum_container | taxonomy_term | Boolean (Locked) | Forums | |
| user_picture | user | Image | User | |



in admin tab of settings create a section with a checkbox - hide 'Body Format'.  have it checked by default.

then the field in the content form will be hidden from view.  here is the element for selector reference:
<div class="js-filter-wrapper clearfix js-form-wrapper form-wrapper filter-wrapper" data-drupal-selector="edit-body-0-format" id="edit-body-0-format"><div data-drupal-selector="edit-body-0-format-help" id="edit-body-0-format-help" class="js-form-wrapper form-wrapper filter-help"><a href="/en/filter/tips" target="_blank" data-drupal-selector="edit-body-0-format-help-about" id="edit-body-0-format-help-about" style="display: none;">About text formats</a></div>
<div class="form-item--editor-format js-form-item form-item js-form-type-select form-type--select js-form-item-body-0-format form-item--body-0-format">
      <label for="edit-body-0-format--2" class="form-item__label" style="display: none;">Text format</label>
        <select class="js-filter-list editor form-element--extrasmall form-element--editor-format form-select form-element form-element--type-select" data-drupal-selector="edit-body-0-format" data-editor-for="edit-body-0-value" id="edit-body-0-format--2" name="body[0][format]" data-once="editor">
            <option value="full_html" selected="selected">Full HTML</option>
                <option value="basic_html">Basic HTML</option>
      </select>

        </div>
<div class="js-filter-guidelines js-form-wrapper form-wrapper filter-guidelines" data-drupal-selector="edit-body-0-format-guidelines" id="edit-body-0-format-guidelines" data-once="filter-guidelines"></div>
</div>

update the en.po with the new text instroduced.

on install and or update set all
node__body = 'full_html' and revisions

media__field_description and revisions
