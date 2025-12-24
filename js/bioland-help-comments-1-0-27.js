/**
 * @file
 * Help comments functionality for Bioland module.
 * Adds helpful messages to body and attachments fields.
 * 
 * Note: No module-level state. All state passed via parameters or stored in DOM data attributes.
 * Uses window.biolandGetLogger for logging (loaded via debug_logger dependency).
 */
(function(Drupal, window, document) {
  'use strict';

  /**
   * Get cookie value by name.
   *
   * @param {string} name - Cookie name
   * @returns {string|null} Cookie value or null if not found
   */
  const getCookie = function(name) {
    const value = '; ' + document.cookie;
    const parts = value.split('; ' + name + '=');
    if (parts.length === 2) {
      return parts.pop().split(';').shift();
    }
    return null;
  };

  /**
   * Set cookie with name, value, and days to expire.
   *
   * @param {string} name - Cookie name
   * @param {string} value - Cookie value
   * @param {number} days - Days until expiration
   */
  const setCookie = function(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = 'expires=' + date.toUTCString();
    document.cookie = name + '=' + value + ';' + expires + ';path=/';
  };

  /**
   * Add close button to help message.
   *
   * @param {HTMLElement} helpMessage - The help message element
   * @param {string} cookieName - Cookie name for persistence
   * @param {HTMLElement} infoIcon - The info icon element
   * @param {Object} logger - Logger instance
   */
  const addCloseButton = function(helpMessage, cookieName, infoIcon, logger) {
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
      setCookie(cookieName, 'hidden', 365);
      // Show the associated info icon if provided
      if (infoIcon) {
        infoIcon.style.display = 'inline';
      }
      logger.log('Help comment hidden and saved to cookie');
    });

    // Make help message position relative for absolute positioning of close button
    helpMessage.style.position = 'relative';
    helpMessage.style.paddingRight = '40px';

    helpMessage.appendChild(closeButton);
  };

  /**
   * Add help message below the body field.
   *
   * @param {Object} helpCommentsSettings - Help comments settings from Drupal.
   * @param {Object} logger - Logger instance
   */
  const addBodyFieldHelp = function(helpCommentsSettings, logger) {
    // Find the label for the body field
    const bodyLabel = document.querySelector('label[for="edit-body-0-value"]');

    if (!bodyLabel) {
      logger.log('Body field label not found for help comment');
      return;
    }

    // Prevent duplicate initialization
    if (bodyLabel.dataset.biolandHelpInit) {
      return;
    }
    bodyLabel.dataset.biolandHelpInit = 'true';

    const cookieName = 'bioland_help_body_hidden';

    // Create clickable wrapper for info icon (FontAwesome replaces <i> with <svg>, losing click handlers)
    const infoIconWrapper = document.createElement('span');
    infoIconWrapper.style.cursor = 'pointer';
    infoIconWrapper.style.marginLeft = '8px';
    infoIconWrapper.style.position = 'relative';
    infoIconWrapper.style.zIndex = '10';
    infoIconWrapper.setAttribute('aria-label', 'Toggle help message');
    infoIconWrapper.setAttribute('role', 'button');
    infoIconWrapper.setAttribute('tabindex', '0');
    
    // Add info icon inside wrapper
    const infoIcon = document.createElement('i');
    infoIcon.className = 'fa-solid fa-info-circle';
    infoIcon.innerHTML = '&nbsp;';
    infoIconWrapper.appendChild(infoIcon);
    bodyLabel.appendChild(infoIconWrapper);

    // Get translated strings from Drupal settings or use defaults
    const helpTitle = Drupal.t('Help');
    const helpText = (helpCommentsSettings && helpCommentsSettings.bodyText)
      ? helpCommentsSettings.bodyText
      : Drupal.t('This will be the main content of your new site content. The summary can be used to display a brief concise description of your content. Further, the summary will be displayed in list and card views of your record on the website. Alternatively, the first few sentences from the main content will be used.');

    // Create help message element
    const helpMessage = document.createElement('p');
    helpMessage.className = 'alert alert-info bioland-help-comment fieldset_description';
    helpMessage.innerHTML = '<big><b><i class="fa-solid fa-info-circle">&nbsp;</i> ' + helpTitle + ' </b></big><br><span>' + helpText + '</span>';
    helpMessage.style.marginRight = '24px';
    helpMessage.style.fontSize = '0.8em';

    // Check cookie to see if help is hidden
    if (getCookie(cookieName) === 'hidden') {
      helpMessage.style.display = 'none';
      infoIconWrapper.style.display = 'inline';
      logger.log('Body field help comment is hidden by user preference');
    } else {
      infoIconWrapper.style.display = 'none';
    }

    // Add close button
    addCloseButton(helpMessage, cookieName, infoIconWrapper, logger);

    // Insert after the label
    bodyLabel.parentElement.insertBefore(helpMessage, bodyLabel.nextSibling);

    // Add click handler to wrapper (survives FontAwesome SVG replacement)
    infoIconWrapper.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      logger.log('>>> BODY INFO ICON CLICKED <<<');
      logger.log('Body info icon clicked, current display:', helpMessage.style.display);
      if (helpMessage.style.display === 'none') {
        helpMessage.style.display = 'block';
        infoIconWrapper.style.display = 'none';
        setCookie(cookieName, 'visible', 365);
        logger.log('Body field help comment SHOWN');
      } else {
        helpMessage.style.display = 'none';
        infoIconWrapper.style.display = 'inline';
        setCookie(cookieName, 'hidden', 365);
        logger.log('Body field help comment HIDDEN');
      }
    });

    logger.log('Body field help comment added');
  };

  /**
   * Add help message to the attachments field.
   *
   * @param {Object} helpCommentsSettings - Help comments settings from Drupal.
   * @param {Object} logger - Logger instance
   */
  const addAttachmentsFieldHelp = function(helpCommentsSettings, logger) {
    // Look for attachments field wrapper
    const attachmentsWrapper = document.querySelector('#field_attachments-media-library-wrapper');

    if (!attachmentsWrapper) {
      logger.log('Attachments field wrapper not found for help comment');
      return;
    }

    // Find the legend element
    const legend = attachmentsWrapper.querySelector('legend');

    if (!legend) {
      logger.log('Attachments legend not found for help comment');
      return;
    }

    // Prevent duplicate initialization
    if (legend.dataset.biolandHelpInit) {
      return;
    }
    legend.dataset.biolandHelpInit = 'true';

    const cookieName = 'bioland_help_attachments_hidden';

    // Create clickable wrapper for info icon (FontAwesome replaces <i> with <svg>, losing click handlers)
    const infoIconWrapper = document.createElement('span');
    infoIconWrapper.style.cursor = 'pointer';
    infoIconWrapper.style.marginLeft = '8px';
    infoIconWrapper.style.position = 'relative';
    infoIconWrapper.style.zIndex = '10';
    infoIconWrapper.setAttribute('aria-label', 'Toggle help message');
    infoIconWrapper.setAttribute('role', 'button');
    infoIconWrapper.setAttribute('tabindex', '0');
    
    // Add info icon inside wrapper
    const infoIcon = document.createElement('i');
    infoIcon.className = 'fa-solid fa-info-circle';
    infoIcon.innerHTML = '&nbsp;';
    infoIconWrapper.appendChild(infoIcon);

    // Check if legend has a span child (Drupal fieldset structure) or append directly
    const legendSpan = legend.querySelector('span');
    if (legendSpan) {
      legendSpan.appendChild(infoIconWrapper);
    } else {
      legend.appendChild(infoIconWrapper);
    }

    // Get translated strings from Drupal settings or use defaults
    const helpTitle = Drupal.t('Help');
    const imagesTitle = Drupal.t('Images');
    const imagesText = (helpCommentsSettings && helpCommentsSettings.attachmentsImagesText)
      ? helpCommentsSettings.attachmentsImagesText
      : Drupal.t('The first image in order of left to right here, will be the main image of your record displayed on the page and in thumbnails in list and card views. All other images will be displayed below the main content.');
    const heroesTitle = Drupal.t('Heroes');
    const heroesText = (helpCommentsSettings && helpCommentsSettings.attachmentsHeroesText)
      ? helpCommentsSettings.attachmentsHeroesText
      : Drupal.t('Any page/content type can have multiple hero banners. If there is more than one they will be rotated on an hourly basis.');

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
    if (getCookie(cookieName) === 'hidden') {
      helpMessage.style.display = 'none';
      infoIconWrapper.style.display = 'inline';
      logger.log('Attachments field help comment is hidden by user preference');
    } else {
      infoIconWrapper.style.display = 'none';
    }

    // Add close button
    addCloseButton(helpMessage, cookieName, infoIconWrapper, logger);

    // Insert after the legend element
    legend.parentElement.insertBefore(helpMessage, legend.nextSibling);

    // Add click handler to wrapper (survives FontAwesome SVG replacement)
    infoIconWrapper.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      logger.log('>>> ATTACHMENTS INFO ICON CLICKED <<<');
      logger.log('Attachments info icon clicked, current display:', helpMessage.style.display);
      if (helpMessage.style.display === 'none') {
        helpMessage.style.display = 'block';
        infoIconWrapper.style.display = 'none';
        setCookie(cookieName, 'visible', 365);
        logger.log('Attachments field help comment SHOWN');
      } else {
        helpMessage.style.display = 'none';
        infoIconWrapper.style.display = 'inline';
        setCookie(cookieName, 'hidden', 365);
        logger.log('Attachments field help comment HIDDEN');
      }
    });

    logger.log('Attachments field help comment added');
  };

  /**
   * Add help message for the Promotion Options section.
   *
   * @param {Object} helpCommentsSettings - Help comments settings from Drupal.
   * @param {Object} logger - Logger instance
   */
  const addPromotionOptionsHelp = function(helpCommentsSettings, logger) {
    // Find the promote field wrapper
    const promoteWrapper = document.querySelector('[data-drupal-selector="edit-promote-wrapper"]');

    if (!promoteWrapper) {
      logger.log('Promote field wrapper not found for help comment');
      return;
    }

    // Prevent duplicate initialization
    if (promoteWrapper.dataset.biolandHelpInit) {
      return;
    }
    promoteWrapper.dataset.biolandHelpInit = 'true';

    const cookieName = 'bioland_help_promotion_hidden';

    // Get translated strings from Drupal settings or use defaults
    const helpTitle = Drupal.t('How it Works');
    const helpText = (helpCommentsSettings && helpCommentsSettings.promotionText)
      ? helpCommentsSettings.promotionText
      : '<b>' + Drupal.t('Promoted to front page:') + '</b>' +
        '<ul>' +
        '<li>' + Drupal.t('Content appears in featured listings, homepage blocks, or "promoted" views') + '</li>' +
        '<li>' + Drupal.t('Useful for highlighting important or timely content') + '</li>' +
        '<li>' + Drupal.t('Multiple items can be promoted simultaneously') + '</li>' +
        '</ul>' +
        '<b>' + Drupal.t('Sticky at top of lists:') + '</b>' +
        '<ul>' +
        '<li>' + Drupal.t('Content stays pinned at the top of content listings') + '</li>' +
        '<li>' + Drupal.t('Remains at top regardless of publication date') + '</li>' +
        '<li>' + Drupal.t('Multiple sticky items appear before non-sticky content') + '</li>' +
        '</ul>' +
        '<b>' + Drupal.t('Using both:') + '</b> ' + Drupal.t('Content can be both promoted AND sticky for maximum visibility.');

    // Create help message element
    const helpMessage = document.createElement('div');
    helpMessage.className = 'alert alert-info bioland-help-comment fieldset_description';
    helpMessage.innerHTML = '<big><b><i class="fa-solid fa-info-circle">&nbsp;</i> ' + helpTitle + ' </b></big><br><br>' + helpText;
    helpMessage.style.marginBottom = '16px';
    helpMessage.style.fontSize = '0.8em';

    // Create clickable wrapper for info icon (FontAwesome replaces <i> with <svg>, losing click handlers)
    const infoIconWrapper = document.createElement('span');
    infoIconWrapper.style.cursor = 'pointer';
    infoIconWrapper.style.marginLeft = '8px';
    infoIconWrapper.style.fontSize = '14px';
    infoIconWrapper.style.position = 'relative';
    infoIconWrapper.style.zIndex = '10';
    infoIconWrapper.setAttribute('aria-label', 'Toggle help message');
    infoIconWrapper.setAttribute('role', 'button');
    infoIconWrapper.setAttribute('tabindex', '0');
    
    // Add info icon inside wrapper
    const infoIcon = document.createElement('i');
    infoIcon.className = 'fa-solid fa-info-circle';
    infoIcon.innerHTML = '&nbsp;';
    infoIconWrapper.appendChild(infoIcon);

    // Check cookie to see if help is hidden
    if (getCookie(cookieName) === 'hidden') {
      helpMessage.style.display = 'none';
      infoIconWrapper.style.display = 'inline';
      logger.log('Promotion options help comment is hidden by user preference');
    } else {
      infoIconWrapper.style.display = 'none';
    }

    // Add close button
    addCloseButton(helpMessage, cookieName, infoIconWrapper, logger);

    // Find the parent container (claro-details__content)
    const parentContainer = promoteWrapper.parentElement;
    
    // Find the summary element to append the info icon to
    const detailsElement = parentContainer ? parentContainer.closest('details') : null;
    const summaryElement = detailsElement ? detailsElement.querySelector('summary') : null;
    
    if (parentContainer) {
      // Insert help message as the first child of the container
      parentContainer.insertBefore(helpMessage, parentContainer.firstChild);
      
      // Insert info icon wrapper into the summary element (so it's visible when collapsed)
      if (summaryElement) {
        summaryElement.appendChild(infoIconWrapper);
      } else {
        // Fallback: insert after help message if summary not found
        parentContainer.insertBefore(infoIconWrapper, helpMessage.nextSibling);
      }
    }

    // Add click handler to wrapper (survives FontAwesome SVG replacement)
    infoIconWrapper.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation(); // Prevent triggering the summary toggle
      logger.log('>>> PROMOTION INFO ICON CLICKED <<<');
      logger.log('Promotion info icon clicked, current display:', helpMessage.style.display);
      if (helpMessage.style.display === 'none') {
        helpMessage.style.display = 'block';
        infoIconWrapper.style.display = 'none';
        setCookie(cookieName, 'visible', 365);
        // Ensure the details element is open so user can see the help
        if (detailsElement && !detailsElement.open) {
          detailsElement.open = true;
        }
        logger.log('Promotion options help comment SHOWN');
      } else {
        helpMessage.style.display = 'none';
        infoIconWrapper.style.display = 'inline';
        setCookie(cookieName, 'hidden', 365);
        logger.log('Promotion options help comment HIDDEN');
      }
    });

    logger.log('Promotion options help comment added');
  };

  /**
   * Add help message for the Order Override field.
   *
   * @param {Object} helpCommentsSettings - Help comments settings from Drupal.
   * @param {Object} logger - Logger instance
   */
  const addOrderOverrideHelp = function(helpCommentsSettings, logger) {
    // Find the order field wrapper inside the Order Override details section
    const orderFieldWrapper = document.querySelector('[data-drupal-selector="edit-field-order-wrapper"]') ||
                              document.querySelector('.field--name-field-order');

    if (!orderFieldWrapper) {
      logger.log('Order field wrapper not found for help comment');
      return;
    }

    // Prevent duplicate initialization
    if (orderFieldWrapper.dataset.biolandHelpInit) {
      return;
    }
    orderFieldWrapper.dataset.biolandHelpInit = 'true';

    const cookieName = 'bioland_help_order_override_hidden';

    // Get translated strings from Drupal settings or use defaults
    const helpTitle = Drupal.t('How it Works');
    const helpText = (helpCommentsSettings && helpCommentsSettings.orderOverrideText)
      ? helpCommentsSettings.orderOverrideText
      : '<b>' + Drupal.t('Content sorting priority (highest to lowest):') + '</b>' +
        '<ol>' +
        '<li><b>' + Drupal.t('Sticky') + '</b> – ' + Drupal.t('Items marked "Sticky at top of lists" always appear first') + '</li>' +
        '<li><b>' + Drupal.t('Order Override') + '</b> – ' + Drupal.t('This field! Lower numbers = higher priority') + '</li>' +
        '<li><b>' + Drupal.t('Promoted') + '</b> – ' + Drupal.t('Items marked "Promoted to front page" come next') + '</li>' +
        '<li><b>' + Drupal.t('Start Date') + '</b> – ' + Drupal.t('Items with future start dates are prioritized') + '</li>' +
        '<li><b>' + Drupal.t('Last Modified') + '</b> – ' + Drupal.t('Most recently edited items appear higher') + '</li>' +
        '</ol>' +
        '<b>' + Drupal.t('Tip:') + '</b> ' + Drupal.t('Use increments of 10 (10, 20, 30...) to leave room for inserting items later.');

    // Create help message element
    const helpMessage = document.createElement('div');
    helpMessage.className = 'alert alert-info bioland-help-comment fieldset_description';
    helpMessage.innerHTML = '<big><b><i class="fa-solid fa-info-circle">&nbsp;</i> ' + helpTitle + ' </b></big><br><br>' + helpText;
    helpMessage.style.marginBottom = '16px';
    helpMessage.style.fontSize = '0.8em';

    // Create clickable wrapper for info icon (FontAwesome replaces <i> with <svg>, losing click handlers)
    const infoIconWrapper = document.createElement('span');
    infoIconWrapper.style.cursor = 'pointer';
    infoIconWrapper.style.marginLeft = '8px';
    infoIconWrapper.style.fontSize = '14px';
    infoIconWrapper.style.position = 'relative';
    infoIconWrapper.style.zIndex = '10';
    infoIconWrapper.setAttribute('aria-label', 'Toggle help message');
    infoIconWrapper.setAttribute('role', 'button');
    infoIconWrapper.setAttribute('tabindex', '0');
    
    // Add info icon inside wrapper
    const infoIcon = document.createElement('i');
    infoIcon.className = 'fa-solid fa-info-circle';
    infoIcon.innerHTML = '&nbsp;';
    infoIconWrapper.appendChild(infoIcon);

    // Check cookie to see if help is hidden
    if (getCookie(cookieName) === 'hidden') {
      helpMessage.style.display = 'none';
      infoIconWrapper.style.display = 'inline';
      logger.log('Order override help comment is hidden by user preference');
    } else {
      infoIconWrapper.style.display = 'none';
    }

    // Add close button
    addCloseButton(helpMessage, cookieName, infoIconWrapper, logger);

    // Find the parent container (claro-details__content) and details element
    const parentContainer = orderFieldWrapper.parentElement;
    
    // Find the outer details element (edit-order-override-wrapper) and its summary
    const detailsElement = parentContainer ? parentContainer.closest('details') : null;
    const summaryElement = detailsElement ? detailsElement.querySelector('summary') : null;
    
    if (parentContainer) {
      // Insert help message as the first child of the container (before the field)
      parentContainer.insertBefore(helpMessage, parentContainer.firstChild);
      
      // Insert info icon wrapper into the summary element (so it's visible when collapsed)
      if (summaryElement) {
        summaryElement.appendChild(infoIconWrapper);
      } else {
        // Fallback: insert after help message if summary not found
        parentContainer.insertBefore(infoIconWrapper, helpMessage.nextSibling);
      }
    }

    // Add click handler to wrapper (survives FontAwesome SVG replacement)
    infoIconWrapper.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation(); // Prevent triggering the summary toggle
      logger.log('>>> ORDER OVERRIDE INFO ICON CLICKED <<<');
      logger.log('Order override info icon clicked, current display:', helpMessage.style.display);
      if (helpMessage.style.display === 'none') {
        helpMessage.style.display = 'block';
        infoIconWrapper.style.display = 'none';
        setCookie(cookieName, 'visible', 365);
        // Ensure the details element is open so user can see the help
        if (detailsElement && !detailsElement.open) {
          detailsElement.open = true;
        }
        logger.log('Order override help comment SHOWN');
      } else {
        helpMessage.style.display = 'none';
        infoIconWrapper.style.display = 'inline';
        setCookie(cookieName, 'hidden', 365);
        logger.log('Order override help comment HIDDEN');
      }
    });

    logger.log('Order override help comment added');
  };

  /**
   * Initialize help comments functionality.
   *
   * @param {Element} context - The context element
   * @param {Object} settings - Bioland settings from PHP
   * @param {Object} logger - Logger instance
   */
  const initializeHelpComments = function(context, settings, logger) {
    logger.log('Initializing help comments');

    // Prevent duplicate initialization using data attribute
    // Check for both create form and edit form
    const form = document.querySelector('form.node-content-form, form.node-content-edit-form');
    if (!form) {
      logger.log('Content form not found for help comments');
      return;
    }

    if (form.dataset.biolandHelpCommentsInit) {
      return;
    }
    form.dataset.biolandHelpCommentsInit = 'true';

    // Get help comments settings
    const helpCommentsSettings = settings.helpComments || {};

    // Add help message to body field
    addBodyFieldHelp(helpCommentsSettings, logger);

    // Add help message to attachments field
    addAttachmentsFieldHelp(helpCommentsSettings, logger);

    // Add help message to promotion options
    addPromotionOptionsHelp(helpCommentsSettings, logger);

    // Add help message to order override field
    addOrderOverrideHelp(helpCommentsSettings, logger);
  };

  /**
   * Drupal behavior for Bioland help comments.
   */
  Drupal.behaviors.biolandHelpComments = {
    attach: function(context, settings) {
      const biolandSettings = settings.bioland || {};
      const logger = window.biolandGetLogger('helpComments', biolandSettings);

      if (biolandSettings.enableHelpComments === false) {
        return;
      }

      initializeHelpComments(context, biolandSettings, logger);
    }
  };

})(Drupal, window, document);


