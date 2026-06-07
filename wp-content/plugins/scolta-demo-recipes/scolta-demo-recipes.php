<?php
/**
 * Plugin Name: Scolta Demo — Recipes
 * Plugin URI: https://github.com/tag1consulting/scolta
 * Description: Registers the Recipe custom post type, taxonomies, and Scolta AI search tuning for the demo.
 * Version: 2.0.0
 * Author: Tag1 Consulting
 * Author URI: https://www.tag1.com
 * License: GPL-2.0-or-later
 * Text Domain: scolta-demo-recipes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =========================================================================
// 1. Custom Post Type & Taxonomies
// =========================================================================

add_action( 'init', 'scolta_recipes_register_post_type' );
add_action( 'init', 'scolta_recipes_register_taxonomies' );
add_action( 'init', 'scolta_recipes_register_post_meta' );

/**
 * Register the Recipe custom post type.
 */
function scolta_recipes_register_post_type() {
	$args = array(
		'label'              => _x( 'Recipes', 'post type general name', 'scolta-demo-recipes' ),
		'singular_name'      => _x( 'Recipe', 'post type singular name', 'scolta-demo-recipes' ),
		'public'             => true,
		'has_archive'        => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		'menu_icon'          => 'dashicons-carrot',
		'rewrite'            => array( 'slug' => 'recipe' ),
	);

	register_post_type( 'recipe', $args );
}

/**
 * Register all custom taxonomies for the Recipe post type.
 */
function scolta_recipes_register_taxonomies() {
	// Cuisines (hierarchical)
	register_taxonomy(
		'recipe_cuisine',
		'recipe',
		array(
			'label'              => _x( 'Cuisines', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Cuisine', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => true,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'cuisine' ),
		)
	);

	// Courses (non-hierarchical)
	register_taxonomy(
		'recipe_course',
		'recipe',
		array(
			'label'              => _x( 'Courses', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Course', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'course' ),
		)
	);

	// Diets (non-hierarchical)
	register_taxonomy(
		'recipe_diet',
		'recipe',
		array(
			'label'              => _x( 'Diets', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Diet', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'diet' ),
		)
	);

	// Difficulty Levels (non-hierarchical)
	register_taxonomy(
		'recipe_difficulty',
		'recipe',
		array(
			'label'              => _x( 'Difficulty Levels', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Difficulty', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'difficulty' ),
		)
	);

	// Techniques (non-hierarchical)
	register_taxonomy(
		'recipe_technique',
		'recipe',
		array(
			'label'              => _x( 'Techniques', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Technique', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'technique' ),
		)
	);

	// Recipe Types (hierarchical)
	register_taxonomy(
		'recipe_type',
		'recipe',
		array(
			'label'              => _x( 'Recipe Types', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Recipe Type', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => true,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'recipe-type' ),
		)
	);

	// Ingredients (non-hierarchical, tag-style)
	register_taxonomy(
		'recipe_ingredient',
		'recipe',
		array(
			'label'              => _x( 'Ingredients', 'taxonomy general name', 'scolta-demo-recipes' ),
			'singular_name'      => _x( 'Ingredient', 'taxonomy singular name', 'scolta-demo-recipes' ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'ingredient' ),
		)
	);
}

/**
 * Register post meta fields for the Recipe post type.
 */
function scolta_recipes_register_post_meta() {
	register_post_meta(
		'recipe',
		'source_url',
		array(
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'recipe',
		'license',
		array(
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => function() {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

// =========================================================================
// 2. WordPress Default Search Compatibility
// =========================================================================

add_action( 'pre_get_posts', 'scolta_recipes_include_in_search' );

function scolta_recipes_include_in_search( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		$query->set( 'post_type', array( 'post', 'page', 'recipe' ) );
	}
}

/**
 * Blocksy theme compatibility: inject ct_post_type parameter into search forms.
 */
add_filter( 'get_search_form', 'scolta_recipes_blocksy_search_form' );

function scolta_recipes_blocksy_search_form( $form ) {
	$hidden = '<input type="hidden" name="ct_post_type" value="post:page:recipe">';
	$form = str_replace( '</form>', $hidden . '</form>', $form );
	return $form;
}

/**
 * Display result count at the top of search results pages.
 */
add_action( 'wp_head', 'scolta_recipes_search_result_count' );

function scolta_recipes_search_result_count() {
	if ( ! is_search() ) {
		return;
	}
	$count = $GLOBALS['wp_query']->found_posts;
	$term  = esc_attr( get_search_query() );
	?>
	<style>.scolta-result-count { padding: 0 0 0.5em; font-size: 0.95em; color: #666; }</style>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var archive = document.querySelector('[class*="entries"]') || document.querySelector('.search-results');
		if (archive) {
			var el = document.createElement('p');
			el.className = 'scolta-result-count';
			el.innerHTML = '<?php echo number_format_i18n( $count ); ?> results for &ldquo;<?php echo $term; ?>&rdquo;';
			archive.parentNode.insertBefore(el, archive);
		}
	});
	</script>
	<?php
}

// =========================================================================
// 3. Scolta Search Index: Inject Taxonomy Data
// =========================================================================

/**
 * Append taxonomy terms to the indexed content so Pagefind can search them.
 *
 * Without this, taxonomy metadata (cuisine, diet, course, etc.) only exists
 * in WordPress term relationships and is invisible to the search index.
 * This is the single highest-leverage fix for search quality.
 */
add_filter( 'scolta_content_item', 'scolta_recipes_enrich_index', 10, 2 );

function scolta_recipes_enrich_index( $item, $post ) {
	if ( $post->post_type !== 'recipe' ) {
		return $item;
	}

	// All taxonomies are appended to the searchable body for full-text recall.
	$taxonomies = array(
		'recipe_cuisine'    => 'Cuisine',
		'recipe_course'     => 'Course',
		'recipe_diet'       => 'Diet',
		'recipe_difficulty' => 'Difficulty',
		'recipe_technique'  => 'Technique',
		'recipe_type'       => 'Recipe Type',
		'recipe_ingredient' => 'Ingredients',
	);

	// A subset is also emitted as Pagefind filter facets (filterable dimensions
	// in the search UI). Keys here must match the `filter_fields` setting. Each
	// is multi-value: a recipe can carry several cuisines, courses, or diets.
	$facet_taxonomies = array(
		'recipe_cuisine'    => 'cuisine',
		'recipe_course'     => 'course',
		'recipe_diet'       => 'diet',
		'recipe_difficulty' => 'difficulty',
	);

	$taxonomy_text = '';
	$filters       = array();

	foreach ( $taxonomies as $tax_slug => $label ) {
		$terms = wp_get_post_terms( $post->ID, $tax_slug, array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		// Searchable body text (all taxonomies).
		$taxonomy_text .= '<p>' . esc_html( $label ) . ': ' . esc_html( implode( ', ', $terms ) ) . '</p>';

		// Facet values (subset only). Multi-value array of term names.
		if ( isset( $facet_taxonomies[ $tax_slug ] ) ) {
			$filters[ $facet_taxonomies[ $tax_slug ] ] = array_values( $terms );
		}
	}

	if ( $taxonomy_text === '' && empty( $filters ) ) {
		return $item;
	}

	return $item->cloneWith(
		array(
			'bodyHtml' => $item->bodyHtml . $taxonomy_text,
			'filters'  => $filters,
		)
	);
}

// =========================================================================
// 4. Scolta AI: Custom Prompts
// =========================================================================

/**
 * Customize Scolta AI prompts for the recipe domain.
 *
 * Three prompt hooks: expand_query, summarize, follow_up.
 * These give the AI context about the recipe collection so it writes
 * confident recommendations instead of hedging with "results don't address."
 *
 * Re-enabled for Round 3.5 testing — testing v0.3.5 with custom prompts but default scoring.
 */
add_filter( 'scolta_prompt', 'scolta_recipes_custom_prompts', 10, 3 );

function scolta_recipes_custom_prompts( $prompt, $promptName, $context ) {

	if ( $promptName === 'summarize' ) {
		$prompt .= "\n\n";
		$prompt .= "This is a recipe collection with 3,700+ recipes from around the world. ";
		$prompt .= "Every recipe has structured metadata: cuisine, course, diet, difficulty, technique, and ingredients. ";

		// Breadth over depth — curate a shortlist, don't write a food blog post.
		$prompt .= "IMPORTANT: Recommend 4–6 recipes minimum. Never deep-dive into a single recipe. ";
		$prompt .= "For each recipe, give the name (as a link), its cuisine or origin, and ONE sentence on why it fits the query. ";
		$prompt .= "Do not list ingredients, measurements, or cooking instructions — that's what the recipe page is for. ";

		// Diversity is mandatory.
		$prompt .= "If the results span multiple cuisines, you MUST highlight that range — ";
		$prompt .= "show the user that this collection covers Thai, Mexican, Indian, Italian, African, and more, not just one tradition. ";

		// Confidence — no hedging.
		$prompt .= "Do not say 'the results don't directly address this' if you can identify recipes that match the user's intent, ";
		$prompt .= "even if the match is indirect (e.g., a warming soup matches 'something for a cold night'). ";

		// Tone.
		$prompt .= "Be a knowledgeable friend giving a confident shortlist, not a cautious search engine. ";
		$prompt .= "Your job is to make the user say 'oh, that's exactly what I wanted' — show range, not detail.";
	}

	if ( $promptName === 'expand_query' ) {
		$prompt .= "\n\n";
		$prompt .= "This is a recipe catalog with 3,700+ recipes organized by cuisine, course, diet, difficulty, technique, and ingredients. ";
		$prompt .= "When expanding queries, include specific recipe-relevant terms: ";
		$prompt .= "cuisine names (Thai, Mexican, Indian, Italian, Japanese, Ethiopian, etc.), ";
		$prompt .= "technique terms (braised, grilled, fried, baked, steamed, sautéed, no-cook), ";
		$prompt .= "dietary terms (vegetarian, vegan, gluten-free, dairy-free, kosher), ";
		$prompt .= "course terms (soup, salad, dessert, appetizer, main course, snack, beverage), ";
		$prompt .= "and ingredient names. ";
		$prompt .= "For regional queries like 'Southeast Asian,' expand to specific cuisines (Thai, Vietnamese, Indonesian, Malaysian, Filipino). ";
		$prompt .= "For sensory queries like 'sweet and spicy,' expand to cuisines and ingredients known for that profile (Thai, Korean, Sichuan, honey, chili, ginger). ";
		$prompt .= "For cross-cultural analogy queries like 'Indian version of a pancake' or 'Japanese equivalent of a burrito,' ";
		$prompt .= "expand to the ACTUAL DISH NAMES from that cuisine (e.g., dosa, appam, uttapam, cheela for Indian pancakes; ";
		$prompt .= "onigirazu, temaki for Japanese burritos). The user doesn't know the name — that's why they're searching. ";
		$prompt .= "Your job is to bridge the gap by adding the specific dish names they can't think of.";
	}

	if ( $promptName === 'follow_up' ) {
		$prompt .= "\n\n";
		$prompt .= "This is a recipe site. Help the user narrow down or explore recipes by asking about or filtering by ";
		$prompt .= "cuisine, dietary needs, difficulty level, cooking technique, or ingredients they have on hand. ";
		$prompt .= "Be conversational and helpful — act as a knowledgeable cooking companion.";
	}

	return $prompt;
}

// =========================================================================
// 5. Plugin Activation
// =========================================================================

register_activation_hook( __FILE__, 'scolta_recipes_activation' );

/**
 * Activation hook: register CPT, flush rewrite rules, configure Scolta settings.
 */
function scolta_recipes_activation() {
	// Ensure CPT and taxonomies are registered before flushing.
	scolta_recipes_register_post_type();
	scolta_recipes_register_taxonomies();

	// Flush rewrite rules.
	flush_rewrite_rules();

	// Configure Scolta settings for the recipe catalog.
	scolta_recipes_configure_scolta();
}

/**
 * Apply Scolta settings tuned for a recipe catalog.
 *
 * Runs on activation. Can also be triggered manually:
 *   wp eval 'scolta_recipes_configure_scolta();'
 */
function scolta_recipes_configure_scolta() {
	$settings = get_option( 'scolta_settings', array() );

	// --- Scoring: Recipe catalog preset (from Scolta docs) ---
	// COMMENTED OUT for Round 3 testing — testing Scolta v0.3.4 defaults.
	// Recency is meaningless — recipe dates are arbitrary Wikibooks source dates.
	// $settings['recency_strategy'] = 'none';
	// Boost title matches — recipe titles are highly descriptive.
	// $settings['title_match_boost']        = 1.5;
	// $settings['title_all_terms_multiplier'] = 2.0;

	// --- Query expansion weight ---
	// COMMENTED OUT for Round 3 testing — testing Scolta v0.3.4 defaults.
	// Lower from 0.7 → 0.5 so AI-expanded terms (e.g., "soup" from "warm me up")
	// carry more weight than the user's literal words.
	// $settings['expand_primary_weight'] = 0.5;

	// --- AI context: send more data to the LLM ---
	// COMMENTED OUT for Round 3 testing — testing Scolta v0.3.4 defaults.
	// $settings['ai_summary_top_n']     = 10;   // default 5
	// $settings['ai_summary_max_chars'] = 4000;  // default 2000

	// --- Site identity for AI prompts ---
	$settings['site_description'] = 'A recipe collection with 3,700+ recipes from around the world, '
		. 'organized by cuisine, course (appetizer, main, dessert, etc.), dietary restrictions '
		. '(vegetarian, vegan, gluten-free), difficulty level, cooking technique, recipe type, '
		. 'and ingredients. Recipes span dozens of cuisines including Thai, Indian, Mexican, '
		. 'Italian, Japanese, Ethiopian, and many more.';

	update_option( 'scolta_settings', $settings );
}
