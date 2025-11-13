/**
 * @file
 * Help comments functionality for Bioland module.
 * Adds helpful messages to body and attachments fields.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Drupal behavior for Bioland help comments.
   */
  Drupal.behaviors.biolandHelpComments = {
    attach: function (context, settings) {
      // Get settings from Drupal
      const biolandSettings = settings.bioland || {};
      
      // Only proceed if help comments is enabled
      if (biolandSettings.enableHelpComments === false) {
        return;
      }

      // Initialize help comments functionality
      this.initializeHelpComments(context, biolandSettings);
    },

    /**
     * Initialize help comments functionality.
     *
     * @param {Element} context - The context element
     * @param {Object} settings - Bioland settings from PHP
     */
    initializeHelpComments: function (context, settings) {
      console.log('Bioland: Initializing help comments');
      
      // Prevent duplicate initialization using data attribute
      // Check for both create form and edit form
      const form = document.querySelector('form.node-content-form, form.node-content-edit-form');
      if (!form) {
        console.log('Bioland: Content form not found for help comments');
        return;
      }

      if (form.dataset.biolandHelpCommentsInit) {
        return;
      }
      form.dataset.biolandHelpCommentsInit = 'true';

      // Add help message to body field
      this.addBodyFieldHelp();

      // Add help message to attachments field
      this.addAttachmentsFieldHelp();
    },

    /**
     * Get cookie value by name.
     */
    getCookie: function (name) {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      if (parts.length === 2) {
        return parts.pop().split(';').shift();
      }
      return null;
    },

    /**
     * Set cookie with name, value, and days to expire.
     */
    setCookie: function (name, value, days) {
      const date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      const expires = `expires=${date.toUTCString()}`;
      document.cookie = `${name}=${value};${expires};path=/`;
    },

    /**
     * Add close button to help message.
     */
    addCloseButton: function (helpMessage, cookieName, infoIcon) {
      const closeButton = document.createElement('button');
      closeButton.type = 'button';
      closeButton.className = 'bioland-help-close';
      closeButton.innerHTML = '<i class="fa-solid fa-times"></i>';
      closeButton.style.cssText = 'position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer; padding: 0; line-height: 1; color: inherit; opacity: 0.6;';
      closeButton.setAttribute('aria-label', 'Close help message');
      
      // Add hover effect
      closeButton.addEventListener('mouseenter', function() {
        this.style.opacity = '1';
      });
      closeButton.addEventListener('mouseleave', function() {
        this.style.opacity = '0.6';
      });

      // Add click handler
      closeButton.addEventListener('click', function() {
        helpMessage.style.display = 'none';
        this.setCookie(cookieName, 'hidden', 365);
        // Show the associated info icon if provided
        if (infoIcon) {
          infoIcon.style.display = 'inline';
        }
        console.log('Bioland: Help comment hidden and saved to cookie');
      }.bind(this));

      // Make help message position relative for absolute positioning of close button
      helpMessage.style.position = 'relative';
      helpMessage.style.paddingRight = '40px';
      
      helpMessage.appendChild(closeButton);
    },

    /**
     * Add help message below the body field.
     */
    addBodyFieldHelp: function () {
      // Find the label for the body field
      const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');
      
      if (!bodyLabel) {
        console.log('Bioland: Body field label not found for help comment');
        return;
      }

      // Prevent duplicate initialization
      if (bodyLabel.dataset.biolandHelpInit) {
        return;
      }
      bodyLabel.dataset.biolandHelpInit = 'true';

      const cookieName = 'bioland_help_body_hidden';

      // Add info icon to label
      const infoIcon = document.createElement('i');
      infoIcon.className = 'fa-solid fa-info-circle';
      infoIcon.innerHTML = '&nbsp;';
      infoIcon.style.cursor = 'pointer';
      infoIcon.style.marginLeft = '8px';
      infoIcon.setAttribute('aria-label', 'Toggle help message');
      bodyLabel.appendChild(infoIcon);

      // Get translated strings from Drupal settings
      const helpTitle = Drupal.t('Help');
      const helpText = Drupal.t('This will be the main content of your new site content.  The summary can be used to display a brief concise description of your content.  Further, the summary  will be displayed in list and card views of your record on the website.  Alternatively, the first few sentence from the main content will be used.');

      // Create help message element
      const helpMessage = document.createElement('p');
      helpMessage.className = 'alert alert-info bioland-help-comment fieldset_description';
      helpMessage.innerHTML = '<big><b><i class="fa-solid fa-info-circle">&nbsp;</i> ' + helpTitle + ' </b></big><br><span>' + helpText + '</span>';
      helpMessage.style.marginRight = '24px';
      helpMessage.style.fontSize = '0.8em';

      // Check cookie to see if help is hidden
      if (this.getCookie(cookieName) === 'hidden') {
        helpMessage.style.display = 'none';
        infoIcon.style.display = 'inline';
        console.log('Bioland: Body field help comment is hidden by user preference');
      } else {
        infoIcon.style.display = 'none';
      }

      // Add close button
      this.addCloseButton(helpMessage, cookieName, infoIcon);

      // Insert after the label
      bodyLabel.parentElement.insertBefore(helpMessage, bodyLabel.nextSibling);

      // Add click handler to info icon to toggle visibility
      infoIcon.addEventListener('click', function(e) {
        e.preventDefault();
        if (helpMessage.style.display === 'none') {
          helpMessage.style.display = 'block';
          infoIcon.style.display = 'none';
          this.setCookie(cookieName, 'visible', 365);
          console.log('Bioland: Body field help comment shown');
        } else {
          helpMessage.style.display = 'none';
          infoIcon.style.display = 'inline';
          this.setCookie(cookieName, 'hidden', 365);
          console.log('Bioland: Body field help comment hidden');
        }
      }.bind(this));
      
      console.log('Bioland: Body field help comment added');
    },

    /**
     * Add help message to the attachments field.
     */
    addAttachmentsFieldHelp: function () {
      // Look for attachments field wrapper
      const attachmentsWrapper = document.querySelector('#field_attachments-media-library-wrapper');
      
      if (!attachmentsWrapper) {
        console.log('Bioland: Attachments field wrapper not found for help comment');
        return;
      }

      // Find the legend element
      const legend = attachmentsWrapper.querySelector('legend');
      
      if (!legend) {
        console.log('Bioland: Attachments legend not found for help comment');
        return;
      }

      // Prevent duplicate initialization
      if (legend.dataset.biolandHelpInit) {
        return;
      }
      legend.dataset.biolandHelpInit = 'true';

      const cookieName = 'bioland_help_attachments_hidden';

      // Add info icon to legend
      const infoIcon = document.createElement('i');
      infoIcon.className = 'fa-solid fa-info-circle';
      infoIcon.innerHTML = '&nbsp;';
      infoIcon.style.cursor = 'pointer';
      infoIcon.style.marginLeft = '8px';
      infoIcon.setAttribute('aria-label', 'Toggle help message');
      
      // Check if legend has a span child (Drupal fieldset structure) or append directly
      const legendSpan = legend.querySelector('span');
      if (legendSpan) {
        legendSpan.appendChild(infoIcon);
      } else {
        legend.appendChild(infoIcon);
      }

      // Get translated strings from Drupal settings
      const helpTitle = Drupal.t('Help');
      const imagesTitle = Drupal.t('Images');
      const imagesText = Drupal.t('The <b>first image</b> in order of left to right here, <b>will be the main image</b> of your record displayed on the page and in thumbnails in list and card views.  All another images will be displayed below the main content.');
      const heroesTitle = Drupal.t('Heroes');
      const heroesText = Drupal.t('Any page/content type can have multiple <b>hero banners</b>.  If there is more then one they will be rotated on an hourly basis.');

      // Create help message element
      const helpMessage = document.createElement('p');
      helpMessage.className = 'alert alert-info bioland-help-comment fieldset_description';
      helpMessage.innerHTML = '<big><b><i class="fa-solid fa-info-circle">&nbsp;</i> ' + helpTitle + ' </b></big><br><br>' +
        '<b>' + imagesTitle + '</b><br>' +
        '<span>' + imagesText + '</span><br><br>' +
        '<b>' + heroesTitle + '</b><br>' +
        '<span>' + heroesText + '</span>';
      helpMessage.style.marginLeft = '24px';
      helpMessage.style.marginRight = '24px';
      helpMessage.style.fontSize = '0.8em';

      // Check cookie to see if help is hidden
      if (this.getCookie(cookieName) === 'hidden') {
        helpMessage.style.display = 'none';
        infoIcon.style.display = 'inline';
        console.log('Bioland: Attachments field help comment is hidden by user preference');
      } else {
        infoIcon.style.display = 'none';
      }

      // Add close button
      this.addCloseButton(helpMessage, cookieName, infoIcon);

      // Insert after the legend element
      legend.parentElement.insertBefore(helpMessage, legend.nextSibling);

      // Add click handler to info icon to toggle visibility
      infoIcon.addEventListener('click', function(e) {
        e.preventDefault();
        if (helpMessage.style.display === 'none') {
          helpMessage.style.display = 'block';
          infoIcon.style.display = 'none';
          this.setCookie(cookieName, 'visible', 365);
          console.log('Bioland: Attachments field help comment shown');
        } else {
          helpMessage.style.display = 'none';
          infoIcon.style.display = 'inline';
          this.setCookie(cookieName, 'hidden', 365);
          console.log('Bioland: Attachments field help comment hidden');
        }
      }.bind(this));
      
      console.log('Bioland: Attachments field help comment added');
    }
  };

})(jQuery, Drupal);
