<?php
/**
 * Blocksy Child – Recipe
 *
 * Enqueues Google Fonts (Playfair Display + Inter) and adds
 * preconnect resource hints for faster font loading.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the child-theme stylesheet (after Blocksy's own styles) and
 * Google Fonts. Blocksy uses the handle 'blocksy-styles' for its main CSS,
 * so we declare that as a dependency to guarantee correct load order.
 */
add_action( 'wp_enqueue_scripts', function () {
	// Child theme CSS — depends on Blocksy's stylesheet so it loads last.
	wp_enqueue_style(
		'blocksy-child-styles',
		get_stylesheet_directory_uri() . '/style.css',
		[ 'ct-main-styles' ],
		wp_get_theme()->get( 'Version' )
	);

	// Google Fonts.
	wp_enqueue_style(
		'recipe-google-fonts',
		'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@400;500;600&display=swap',
		[],
		null   // No version — Google Fonts caches by URL.
	);
} );

/**
 * Add preconnect hints so the browser opens the Google Fonts connections
 * before it parses the stylesheet.
 *
 * @param array  $hints         Array of resource hints grouped by type.
 * @param string $relation_type The hint relation type (preconnect, dns-prefetch, …).
 * @return array
 */
add_filter( 'wp_resource_hints', function ( array $hints, string $relation_type ): array {
	if ( 'preconnect' === $relation_type ) {
		$hints[] = [ 'href' => 'https://fonts.googleapis.com' ];
		$hints[] = [ 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' ];
	}
	return $hints;
}, 10, 2 );

/**
 * Fix recipe archive page title: "Archives: Recipes" → "Browse Recipes".
 *
 * @param string $title The generated archive title.
 * @return string
 */
add_filter( 'get_the_archive_title', function ( string $title ): string {
	if ( is_post_type_archive( 'recipe' ) ) {
		return 'Browse Recipes';
	}
	return $title;
} );

/**
 * Homepage enhancements:
 *   1. Override the Scolta search placeholder text.
 *   2. Wire up the suggested-search chips to trigger a search.
 *
 * Scolta renders its UI client-side, so we use a MutationObserver to wait
 * for the input element before setting the placeholder.
 */
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	?>
	<script>
	(function () {
		'use strict';

		var PLACEHOLDER = 'Describe what you\u2019re craving\u2026';

		function setPlaceholder() {
			var input = document.querySelector('.scolta-search-box input[type="text"]');
			if ( input && input.placeholder !== PLACEHOLDER ) {
				input.placeholder = PLACEHOLDER;
			}
		}

		// Watch for Scolta rendering the search input.
		var target = document.getElementById('scolta-search') || document.body;
		var observer = new MutationObserver( setPlaceholder );
		observer.observe( target, { childList: true, subtree: true } );
		setPlaceholder(); // also try immediately in case it's already rendered

		// Suggested-search chip clicks.
		document.querySelectorAll('.recipe-suggestion-chip').forEach( function ( chip ) {
			chip.addEventListener('click', function () {
				var query = this.dataset.query;

				// Scroll the search widget into view first.
				var searchEl = document.getElementById('scolta-search');
				if ( searchEl ) {
					searchEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}

				// Short delay to let the scroll settle, then populate and submit.
				setTimeout( function () {
					var input = document.querySelector('.scolta-search-box input[type="text"]');
					if ( ! input ) return;
					input.value = query;
					input.dispatchEvent( new Event('input', { bubbles: true }) );
					var btn = document.querySelector('.scolta-search-btn');
					if ( btn ) btn.click();
				}, 350 );
			} );
		} );
	})();
	</script>
	<?php
} );
