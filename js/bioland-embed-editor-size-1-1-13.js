/**
 * @file
 * BL-1313: sizes embed (inline frame) media previews inside CKEditor 5 and
 * prefills the Media Resize balloon for them.
 *
 * The media_embed preview renders the frame field with width / height
 * attributes (e.g. width="100%" height="75%"). A percentage height attribute
 * has no sized parent inside .drupal-media, so the browser falls back to
 * 150px, and ckeditor_media_resizer only knows how to size images. This
 * behavior gives every embed iframe an inline style computed by the same
 * rules the public page uses (bioland-head app/utils/html.js toSizeStyle(),
 * BL-1314), so the editor preview matches the site.
 *
 * Precedence, per dimension, highest first:
 *   1. CKEditor value: data-media-width / data-media-height on .drupal-media.
 *   2. Field value: width / height attributes on the preview iframe.
 *   3. Fallback: width 100%; no height anywhere means 16:9.
 *
 * Size to style (keep identical to the head):
 *   px + px from the same source  aspect-ratio: W / H; width: 100%
 *   any + missing height          aspect-ratio: 16 / 9; width: <W>
 *   any + % or vh height          width: <W>; height: min(H, 100)vh
 *   any + px, em, rem, vw height  width: <W>; height: <H>
 * A CKEditor % width is already on the wrapper (resizer JS), so the iframe
 * gets width: 100% of it. A CKEditor px + px size is a ratio, so the
 * resizer's inline px width on the wrapper is cleared for embed frames.
 * Mixed sources never form a ratio: the two numbers were not chosen together.
 *
 * Only frames inside .media--type-embed are touched; oEmbed players
 * (iframe.media-oembed-content) keep their BL-1207 CSS rule and images stay
 * with the resizer.
 */
(function (Drupal, once) {
  'use strict';

  var ONCE_ID = 'bioland-embed-editor-size';
  var ROOT_SELECTOR = '.ck-content';
  var FRAME_SELECTOR = '.drupal-media .media--type-embed iframe:not(.media-oembed-content)';
  var SIZED_ATTRIBUTE = 'data-bioland-embed-sized';
  // Units the Media Resize balloon offers (ckeditor_media_resizer UNITS).
  var BALLOON_UNITS = ['px', '%', 'em', 'vw', 'vh'];

  // Observers created by this behavior, so detach can disconnect them.
  var rootObservers = [];
  var bodyObserver = null;

  /**
   * Parses a size into { amount, unit }, mirroring the head's toLength().
   *
   * A plain integer is pixels. Only a number of up to four digits (two
   * decimals) and a known unit is accepted, so nothing else ever reaches a
   * style value.
   */
  function toLength(value) {
    var match = /^\s*(\d{1,4}(?:\.\d{1,2})?)(px|%|em|rem|vw|vh)?\s*$/i.exec(value == null ? '' : String(value));
    if (!match || !Number(match[1])) {
      return undefined;
    }
    return { amount: match[1], unit: (match[2] || 'px').toLowerCase() };
  }

  // A percentage height has no sized parent to resolve against, so it reads
  // as a share of the viewport height, capped at the full screen.
  function toHeight(height) {
    return (height.unit === '%' || height.unit === 'vh')
      ? Math.min(Number(height.amount), 100) + 'vh'
      : height.amount + height.unit;
  }

  function toSizeStyle(width, height, sameSource) {
    if (sameSource && width && height && width.unit === 'px' && height.unit === 'px') {
      return 'aspect-ratio: ' + width.amount + ' / ' + height.amount + '; width: 100%;';
    }
    var widthStyle = width ? 'width: ' + width.amount + width.unit + ';' : 'width: 100%;';
    if (!height) {
      return 'aspect-ratio: 16 / 9; ' + widthStyle;
    }
    return widthStyle + ' height: ' + toHeight(height) + ';';
  }

  // The CKEditor resize values stored on the .drupal-media wrapper.
  function ckDimensions(iframe) {
    var wrapper = iframe.closest('.drupal-media');
    return {
      wrapper: wrapper,
      width: toLength(wrapper && wrapper.getAttribute('data-media-width')),
      height: toLength(wrapper && wrapper.getAttribute('data-media-height'))
    };
  }

  /**
   * Computes the inline style for one embed preview iframe.
   *
   * @param {HTMLIFrameElement} iframe
   *   The preview iframe, inside its .drupal-media wrapper.
   *
   * @return {string}
   *   The style attribute value.
   */
  function sizeEmbedPreview(iframe) {
    return styleFor(iframe, ckDimensions(iframe));
  }

  function styleFor(iframe, ck) {
    var width = ck.width || toLength(iframe.getAttribute('width'));
    var height = ck.height || toLength(iframe.getAttribute('height'));
    if (ck.width && ck.width.unit === '%') {
      width = { amount: '100', unit: '%' };
    }
    return toSizeStyle(width, height, Boolean(ck.width) === Boolean(ck.height));
  }

  // Writes only on a real change: an unchanged setAttribute still queues a
  // mutation record, which would wake the observers again.
  function sizeFrame(iframe) {
    var ck = ckDimensions(iframe);
    var style = styleFor(iframe, ck);
    if (iframe.getAttribute('style') !== style) {
      iframe.setAttribute('style', style);
    }
    if (!iframe.hasAttribute(SIZED_ATTRIBUTE)) {
      iframe.setAttribute(SIZED_ATTRIBUTE, '');
    }
    // A CKEditor px + px size is a ratio of the column, as on the public
    // page, so the resizer's inline px width on the wrapper must go.
    if (ck.wrapper && ck.width && ck.height && ck.width.unit === 'px' && ck.height.unit === 'px' && ck.wrapper.style.width) {
      ck.wrapper.style.width = '';
    }
  }

  function sizeRoot(root) {
    root.querySelectorAll(FRAME_SELECTOR).forEach(sizeFrame);
  }

  function observeRoot(root) {
    sizeRoot(root);
    var observer = new MutationObserver(function () {
      sizeRoot(root);
    });
    // `style` is watched as well so the wrapper width the resizer re-applies
    // after every model change (requestAnimationFrame) is cleared again.
    observer.observe(root, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['data-media-width', 'data-media-height', 'width', 'height', 'style']
    });
    rootObservers.push({ root: root, observer: observer });
  }

  function attachRoots(context) {
    once(ONCE_ID, ROOT_SELECTOR, context).forEach(observeRoot);
  }

  // The embed iframe of the widget currently selected in an editor, if any.
  function selectedEmbedFrame() {
    var selected = document.querySelectorAll(ROOT_SELECTOR + ' .ck-widget_selected');
    for (var i = 0; i < selected.length; i++) {
      var media = selected[i].classList.contains('drupal-media')
        ? selected[i]
        : selected[i].querySelector('.drupal-media');
      var iframe = media && media.querySelector('.media--type-embed iframe:not(.media-oembed-content)');
      if (iframe) {
        return iframe;
      }
    }
    return null;
  }

  function fillInput(input, amount) {
    input.value = amount;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /**
   * Prefills an opened Media Resize form from the embed field value.
   *
   * The resizer's own setValue() has already run (it fills the form before
   * the observer callback), so an empty input means no saved CKEditor value.
   * A non-empty input is never overwritten.
   */
  function prefillBalloon(form) {
    var inputs = form.querySelectorAll('.ck-media-resize-form__row input');
    var widthInput = inputs[0];
    var heightInput = inputs[1];
    if (!widthInput || !heightInput || widthInput.value !== '' || heightInput.value !== '') {
      return;
    }
    var iframe = selectedEmbedFrame();
    if (!iframe) {
      return;
    }
    var width = toLength(iframe.getAttribute('width'));
    var height = toLength(iframe.getAttribute('height'));
    if (!width || BALLOON_UNITS.indexOf(width.unit) === -1) {
      return;
    }
    // _setUnit() clears both inputs, so the unit goes first.
    var unitButton = form.querySelector('.ck-media-resize-form__unit-btn[data-unit="' + width.unit + '"]');
    if (!unitButton) {
      return;
    }
    unitButton.click();
    fillInput(widthInput, width.amount);
    if (height && height.unit === width.unit) {
      fillInput(heightInput, height.amount);
    }
  }

  function onBodyMutations(mutations) {
    for (var i = 0; i < mutations.length; i++) {
      var added = mutations[i].addedNodes;
      for (var j = 0; j < added.length; j++) {
        var node = added[j];
        if (node.nodeType !== 1) {
          continue;
        }
        // CKEditor mounts its editable after behaviors attach.
        if (node.matches(ROOT_SELECTOR) || node.querySelector(ROOT_SELECTOR)) {
          attachRoots(node.parentNode || node);
        }
        var form = node.matches('.ck-media-resize-form') ? node : node.querySelector('.ck-media-resize-form');
        if (form) {
          prefillBalloon(form);
        }
      }
    }
  }

  Drupal.behaviors.biolandEmbedEditorSize = {
    attach: function (context) {
      attachRoots(context);
      // The balloon lives in the lazily created .ck-body-wrapper, outside
      // any form context, so one observer watches the whole body.
      if (!bodyObserver && document.body) {
        bodyObserver = new MutationObserver(onBodyMutations);
        bodyObserver.observe(document.body, { childList: true, subtree: true });
      }
    },

    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      rootObservers = rootObservers.filter(function (entry) {
        if (context === entry.root || context.contains(entry.root)) {
          entry.observer.disconnect();
          // Lets a later attach observe this root again.
          once.remove(ONCE_ID, entry.root);
          return false;
        }
        return true;
      });
      if (bodyObserver && (context === document || context === document.body)) {
        bodyObserver.disconnect();
        bodyObserver = null;
      }
    },

    sizeEmbedPreview: sizeEmbedPreview,
    prefillBalloon: prefillBalloon
  };
})(Drupal, once);
