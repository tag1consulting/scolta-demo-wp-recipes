/**
 * @file
 * Rich recipe cards for the Scolta recipe search.
 *
 * Registers two Scolta renderers: a result renderer that paints a dish
 * photograph and the recipe's course, cuisine and difficulty alongside the
 * title and highlighted excerpt, and a suggestion renderer that puts the same
 * photograph on the search-as-you-type rows. Everything they need comes from
 * the search index — the thumbnail URL and the badge labels ride along in the
 * fragment's meta map, put there by scolta_recipes_enrich_index() in the demo
 * plugin — so neither a card nor a suggestion costs a per-result server call.
 *
 * Load order matters. scolta.js defines window.Scolta when it executes and
 * calls Scolta.init() on DOMContentLoaded, so this file must run after the
 * former and before the latter. The child theme enqueues it at wp_footer with
 * scolta-search as a dependency, which puts it exactly there; registering at
 * top level (not inside a DOMContentLoaded handler) keeps it there.
 */
(function (global) {
  'use strict';

  if (!global.Scolta || typeof global.Scolta.setResultRenderer !== 'function') {
    // A bundle without the render seam is not something to work around here.
    console.warn('[recipe] Scolta.setResultRenderer unavailable; leaving the built-in card in place.');
    return;
  }

  var ENTITIES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  };

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/[&<>"']/g, function (c) { return ENTITIES[c]; });
  }

  /**
   * Escapes a URL for an attribute and neutralizes non-http(s) schemes.
   *
   * The thumbnail URL is written into the index as a root-relative path, but
   * it arrives here as raw index data, so it gets the same treatment Scolta
   * gives the result href rather than an assumption about who wrote it.
   */
  function safeImageUrl(value) {
    var url = String(value === null || value === undefined ? '' : value).trim();
    if (url === '') {
      return '';
    }
    if (/^[a-z][a-z0-9+.-]*:/i.test(url) && !/^https?:/i.test(url)) {
      return '';
    }
    return escapeHtml(url);
  }

  /**
   * Drops the thumbnail when its image fails to load.
   *
   * Adding a class rather than removing the node keeps the handler cheap and
   * lets the stylesheet decide what an imageless card looks like.
   */
  global.recipeScoltaThumbFailed = function (img) {
    var card = img.closest ? img.closest('.recipe-result') : null;
    if (card) {
      card.classList.add('recipe-result--thumb-failed');
    }
  };

  global.recipeScoltaSaytThumbFailed = function (img) {
    var box = img.closest ? img.closest('.recipe-sayt__thumb') : null;
    if (box) {
      box.removeChild(img);
      box.classList.add('recipe-sayt__thumb--empty');
    }
  };

  /**
   * How many badges a card paints. Mirrors the indexer's own cap, which is
   * what actually bounds the string; this is the client-side belt to it.
   */
  var BADGE_LIMIT = 3;

  /**
   * Renders a recipe's facet badges.
   *
   * data.meta.badges is raw index data: a JSON-encoded array of facet values —
   * course, cuisine, difficulty — already ordered, deduplicated and capped by
   * the demo plugin. JSON and not a delimited string because a term name is
   * free text, and this corpus already contains "Meat, Poultry and Seafood".
   *
   * Anything that does not parse into an array counts as no badges. A recipe
   * without them simply shows none — the same graceful path a missing image
   * takes, not a broken card.
   */
  function badges(encoded) {
    if (!encoded) {
      return '';
    }
    var labels;
    try {
      labels = JSON.parse(encoded);
    } catch (e) {
      return '';
    }
    if (!Array.isArray(labels)) {
      return '';
    }
    var out = '';
    for (var i = 0; i < labels.length && i < BADGE_LIMIT; i++) {
      var label = String(labels[i] === null || labels[i] === undefined ? '' : labels[i]).trim();
      if (label !== '') {
        out += '<span class="recipe-result__badge">' + escapeHtml(label) + '</span>';
      }
    }
    return out;
  }

  /**
   * Renders one result.
   *
   * Escaping: every ctx value used here ends in Html, Attr or Text, or is
   * safeUrl, so Scolta has already escaped it exactly as its own card would.
   * Everything read from data.meta — image, image_alt, badges — is raw index
   * data and is escaped here. ctx.query and ctx.highlightTerms are raw and
   * never reach the markup.
   *
   * A recipe with no photograph gets the same card without the thumbnail, not
   * Scolta's built-in one. 228 of the 3743 recipes have none, so this list
   * really does mix the two, and mixing two card designs down it would read as
   * a broken page rather than a designed fallback.
   */
  global.Scolta.setResultRenderer(function (data, ctx) {
    var meta = (data && data.meta) || {};
    var imageUrl = safeImageUrl(meta.image);
    var alt = escapeHtml(meta.image_alt || '');
    var badgeHtml = badges(meta.badges);

    var metaRow = badgeHtml === '' ? ''
      : '<div class="recipe-result__meta">' + badgeHtml + '</div>';

    // The thumbnail is decorative: the title link beside it goes to the same
    // recipe, so it stays out of the tab order and out of the accessible tree.
    var thumb = imageUrl === '' ? ''
      : '<a class="recipe-result__thumb" href="' + ctx.safeUrl + '" target="_blank" rel="noopener"'
        + ' tabindex="-1" aria-hidden="true">'
        + '<img src="' + imageUrl + '" alt="' + alt + '" loading="lazy" decoding="async"'
        + ' onerror="recipeScoltaThumbFailed(this)">'
        + '</a>';

    // target/rel match the built-in card: within one result list, a card with
    // a thumbnail must not open differently from one without.
    return '<div class="scolta-result-card recipe-result">'
      + thumb
      + '<div class="recipe-result__body">'
      + '<a class="scolta-result-title recipe-result__title" href="' + ctx.safeUrl + '"'
      + ' target="_blank" rel="noopener" title="' + ctx.titleAttr + '">' + ctx.titleHtml + '</a>'
      + metaRow
      + '<div class="scolta-result-excerpt recipe-result__excerpt">' + ctx.excerptHtml + '</div>'
      + '</div>'
      + '</div>';
  });

  // Behind its own guard rather than the file-level one: this seam landed
  // after setResultRenderer, so a bundle old enough to lack it still gets the
  // rich cards above, and the dropdown degrades to the themed but imageless
  // rows instead of throwing.
  if (typeof global.Scolta.setSuggestionRenderer !== 'function') {
    return;
  }

  /**
   * Renders one search-as-you-type suggestion row.
   *
   * Returns the row's INNER markup only. The option element around it is the
   * bundle's, and it is what carries the combobox contract — role="option",
   * the stable id the input's aria-activedescendant points at, aria-selected,
   * the data-scolta-sayt-index the keyboard and click handlers dispatch on,
   * and the href in navigate mode. None of that is restated here, because a
   * renderer cannot break by omission what it never writes.
   *
   * Escaping: ctx.titleHtml and ctx.excerptHtml arrive pre-escaped, escaped
   * exactly as the built-in row escapes them. suggestion.meta.* is raw index
   * data and is escaped here. ctx.query is raw and never reaches the markup.
   *
   * A recent search is handed back to the built-in row by returning null: it
   * has no fragment, no photograph and nothing to add, and the built-in row is
   * already the themed glyph treatment this dropdown wants for history.
   */
  global.Scolta.setSuggestionRenderer(function (suggestion, ctx) {
    if (!suggestion || suggestion.type !== 'title') {
      return null;
    }

    var meta = suggestion.meta || {};
    var imageUrl = safeImageUrl(meta.image);

    // Decorative, and deliberately not carrying meta.image_alt: an option's
    // accessible name is computed from its contents, so alt text here would be
    // announced in front of the title it illustrates. The title names the row.
    //
    // A row with no photograph still gets the box, empty and with its border
    // and fill removed. Roughly one recipe in sixteen has none, so without the
    // spacer the dropdown would mix indented and flush-left rows and stop
    // reading as one list.
    var thumb = imageUrl === ''
      ? '<span class="recipe-sayt__thumb recipe-sayt__thumb--empty" aria-hidden="true"></span>'
      : '<span class="recipe-sayt__thumb" aria-hidden="true">'
        + '<img src="' + imageUrl + '" alt="" loading="lazy" decoding="async"'
        + ' onerror="recipeScoltaSaytThumbFailed(this)">'
        + '</span>';

    return '<span class="recipe-sayt">'
      + thumb
      // Both classes on purpose. The scolta-* one carries the look the theme
      // already gives a suggestion's title and excerpt, so a title row and a
      // recent-search row stay typographically identical; the recipe-* one
      // adds only the layout this row needs. Two classes at the same
      // specificity, resolved by source order, rather than a nested selector.
      + '<span class="recipe-sayt__text">'
      + '<span class="scolta-sayt-title recipe-sayt__title">' + ctx.titleHtml + '</span>'
      + (ctx.excerptHtml
        ? '<span class="scolta-sayt-excerpt recipe-sayt__excerpt">' + ctx.excerptHtml + '</span>'
        : '')
      + '</span>'
      + '</span>';
  });

})(window);
