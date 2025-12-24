# Drupal Field Configuration

| Field name | Entity type | Field type | Used in | Summary |
|------------|-------------|------------|---------|---------|
| comment_body | comment | Text (formatted, long) | Comment_content, Comment_forum, Comment_media, Comment_taxonomy | |
| field_description | media | Text (formatted, long) | Remote video, Document, Image, Hero | |
| body | node | Text (formatted, long, with summary) | Site Content, Forum topic | |



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
