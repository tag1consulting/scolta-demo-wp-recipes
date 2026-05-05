#!/usr/bin/env php
<?php
/**
 * WP-CLI Import Script: Import recipe markdown files into WordPress.
 *
 * Usage:
 *   wp eval-file import-recipes.php /path/to/content/recipes/
 *   wp eval-file import-recipes.php /path/to/content/recipes/ --limit=50
 *   wp eval-file import-recipes.php /path/to/content/recipes/ --dry-run
 *
 * Requires:
 *   - The scolta-demo-recipes plugin must be active (provides the 'recipe' CPT and taxonomies)
 *   - WP-CLI must be available
 *
 * What it does:
 *   1. Scans the given directory for .md files
 *   2. Parses YAML frontmatter and markdown body from each file
 *   3. Creates a 'recipe' post for each file (skips duplicates by slug)
 *   4. Assigns taxonomy terms (cuisine, course, diet, difficulty, technique, type, ingredients)
 *   5. Sets post meta (source_url, license)
 *   6. Converts markdown body to HTML for the post content
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must be run via WP-CLI: wp eval-file import-recipes.php /path/to/recipes/\n";
	exit( 1 );
}

if ( ! class_exists( 'WP_CLI' ) ) {
	echo "WP-CLI is required. Run: wp eval-file import-recipes.php /path/to/recipes/\n";
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Parse CLI arguments
// ---------------------------------------------------------------------------

$args     = $GLOBALS['argv'] ?? $args ?? array();
$dir      = '';
$limit    = 0;
$dry_run  = false;

foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, '--limit=' ) ) {
		$limit = (int) substr( $arg, 8 );
	} elseif ( $arg === '--dry-run' ) {
		$dry_run = true;
	} elseif ( ! str_starts_with( $arg, '--' ) && is_dir( $arg ) ) {
		$dir = rtrim( $arg, '/' );
	}
}

if ( empty( $dir ) ) {
	WP_CLI::error( 'Usage: wp eval-file import-recipes.php /path/to/content/recipes/ [--limit=N] [--dry-run]' );
}

if ( ! is_dir( $dir ) ) {
	WP_CLI::error( "Directory not found: $dir" );
}

// Verify the recipe post type exists
if ( ! post_type_exists( 'recipe' ) ) {
	WP_CLI::error( 'The "recipe" post type is not registered. Is the scolta-demo-recipes plugin active?' );
}

// ---------------------------------------------------------------------------
// YAML frontmatter parser (lightweight — no external dependencies)
// ---------------------------------------------------------------------------

/**
 * Parse a markdown file into frontmatter (associative array) and body (string).
 *
 * Handles the standard --- delimited YAML frontmatter format.
 * Uses a simple line-by-line YAML parser (no Symfony/Spyc dependency).
 */
function scolta_parse_markdown_file( string $filepath ): array {
	$raw = file_get_contents( $filepath );
	if ( $raw === false ) {
		return array( 'frontmatter' => array(), 'body' => '' );
	}

	$frontmatter = array();
	$body        = $raw;

	// Check for YAML frontmatter delimiters
	if ( str_starts_with( trim( $raw ), '---' ) ) {
		// Find the closing ---
		$parts = preg_split( '/^---\s*$/m', $raw, 3 );
		if ( count( $parts ) >= 3 ) {
			$yaml_str = $parts[1];
			$body     = $parts[2];

			$frontmatter = scolta_parse_simple_yaml( $yaml_str );
		}
	}

	return array(
		'frontmatter' => $frontmatter,
		'body'        => trim( $body ),
	);
}

/**
 * Simple YAML parser for frontmatter.
 *
 * Handles:
 *   key: value          → string
 *   key: "value"        → string (quotes stripped)
 *   key: 'value'        → string (quotes stripped)
 *   key:                → empty string or start of array
 *     - item1           → array
 *     - item2
 *   key: []             → empty array
 *
 * Does NOT handle nested objects, multi-line strings, or complex YAML.
 * This is sufficient for the Scolta recipe frontmatter schema.
 */
function scolta_parse_simple_yaml( string $yaml ): array {
	$result      = array();
	$current_key = null;
	$lines       = explode( "\n", $yaml );

	foreach ( $lines as $line ) {
		// Skip empty lines and comments
		if ( trim( $line ) === '' || str_starts_with( trim( $line ), '#' ) ) {
			continue;
		}

		// Array item: "- value" or "  - value" (PyYAML doesn't indent list items)
		if ( preg_match( '/^\s*-\s+(.+)$/', $line, $m ) && $current_key !== null ) {
			if ( ! is_array( $result[ $current_key ] ) ) {
				$result[ $current_key ] = array();
			}
			$result[ $current_key ][] = scolta_strip_yaml_quotes( trim( $m[1] ) );
			continue;
		}

		// Key-value pair: "key: value"
		if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $line, $m ) ) {
			$key   = $m[1];
			$value = trim( $m[2] );

			if ( $value === '' ) {
				// Could be start of an array or empty string — set empty array as default
				$result[ $key ] = array();
				$current_key    = $key;
			} elseif ( $value === '[]' ) {
				$result[ $key ] = array();
				$current_key    = $key;
			} else {
				$result[ $key ] = scolta_strip_yaml_quotes( $value );
				$current_key    = $key;
			}
		}
	}

	return $result;
}

/**
 * Strip surrounding quotes from a YAML value.
 */
function scolta_strip_yaml_quotes( string $value ): string {
	if ( strlen( $value ) >= 2 ) {
		if (
			( $value[0] === '"' && $value[ strlen( $value ) - 1 ] === '"' ) ||
			( $value[0] === "'" && $value[ strlen( $value ) - 1 ] === "'" )
		) {
			return substr( $value, 1, -1 );
		}
	}
	return $value;
}

// ---------------------------------------------------------------------------
// Markdown to HTML converter (basic — handles recipe content patterns)
// ---------------------------------------------------------------------------

/**
 * Convert markdown to HTML.
 *
 * Handles the subset of markdown used in Wikibooks recipe files:
 *   # Heading 1, ## Heading 2, ### Heading 3
 *   **bold**, *italic*
 *   - unordered list items
 *   1. ordered list items
 *   Paragraphs (blank-line separated)
 *   [link text](url)
 *
 * For a production site you'd use a proper parser (Parsedown, league/commonmark).
 * This is sufficient for the demo import.
 */
function scolta_markdown_to_html( string $md ): string {
	$lines  = explode( "\n", $md );
	$html   = '';
	$in_ul  = false;
	$in_ol  = false;
	$in_p   = false;
	$buffer = '';

	foreach ( $lines as $line ) {
		$trimmed = trim( $line );

		// Close open lists if this line isn't a list item
		if ( $in_ul && ! preg_match( '/^[-*]\s/', $trimmed ) ) {
			$html .= "</ul>\n";
			$in_ul = false;
		}
		if ( $in_ol && ! preg_match( '/^\d+\.\s/', $trimmed ) ) {
			$html .= "</ol>\n";
			$in_ol = false;
		}

		// Empty line = paragraph break
		if ( $trimmed === '' ) {
			if ( $in_p ) {
				$html .= '<p>' . scolta_inline_md( $buffer ) . "</p>\n";
				$buffer = '';
				$in_p   = false;
			}
			continue;
		}

		// Headings
		if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $m ) ) {
			if ( $in_p ) {
				$html .= '<p>' . scolta_inline_md( $buffer ) . "</p>\n";
				$buffer = '';
				$in_p   = false;
			}
			$level = strlen( $m[1] );
			$text  = scolta_inline_md( trim( $m[2] ) );
			$html .= "<h{$level}>{$text}</h{$level}>\n";
			continue;
		}

		// Unordered list item
		if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $m ) ) {
			if ( $in_p ) {
				$html .= '<p>' . scolta_inline_md( $buffer ) . "</p>\n";
				$buffer = '';
				$in_p   = false;
			}
			if ( ! $in_ul ) {
				$html .= "<ul>\n";
				$in_ul = true;
			}
			$html .= '<li>' . scolta_inline_md( trim( $m[1] ) ) . "</li>\n";
			continue;
		}

		// Ordered list item
		if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $m ) ) {
			if ( $in_p ) {
				$html .= '<p>' . scolta_inline_md( $buffer ) . "</p>\n";
				$buffer = '';
				$in_p   = false;
			}
			if ( ! $in_ol ) {
				$html .= "<ol>\n";
				$in_ol = true;
			}
			$html .= '<li>' . scolta_inline_md( trim( $m[1] ) ) . "</li>\n";
			continue;
		}

		// Regular text — accumulate for paragraph
		if ( $in_p ) {
			$buffer .= ' ' . $trimmed;
		} else {
			$buffer = $trimmed;
			$in_p   = true;
		}
	}

	// Close any open elements
	if ( $in_ul ) {
		$html .= "</ul>\n";
	}
	if ( $in_ol ) {
		$html .= "</ol>\n";
	}
	if ( $in_p && $buffer !== '' ) {
		$html .= '<p>' . scolta_inline_md( $buffer ) . "</p>\n";
	}

	return $html;
}

/**
 * Convert inline markdown to HTML (bold, italic, links).
 */
function scolta_inline_md( string $text ): string {
	// Links: [text](url)
	$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text );
	// Bold: **text**
	$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
	// Italic: *text*
	$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
	return $text;
}

// ---------------------------------------------------------------------------
// Import logic
// ---------------------------------------------------------------------------

// Taxonomy mapping: frontmatter key → WordPress taxonomy slug
$taxonomy_map = array(
	'cuisine'     => 'recipe_cuisine',
	'course'      => 'recipe_course',
	'diet'        => 'recipe_diet',
	'difficulty'  => 'recipe_difficulty',
	'technique'   => 'recipe_technique',
	'recipe_type' => 'recipe_type',
	'ingredients' => 'recipe_ingredient',
);

// Scan for markdown files
$files = glob( "$dir/*.md" );
if ( empty( $files ) ) {
	WP_CLI::error( "No .md files found in $dir" );
}

sort( $files );
$total = count( $files );

if ( $limit > 0 ) {
	$files = array_slice( $files, 0, $limit );
	WP_CLI::log( "Limiting to $limit of $total files" );
}

WP_CLI::log( sprintf( 'Found %d recipe files to import%s', count( $files ), $dry_run ? ' (DRY RUN)' : '' ) );

$created  = 0;
$skipped  = 0;
$errors   = 0;
$progress = \WP_CLI\Utils\make_progress_bar( 'Importing recipes', count( $files ) );

foreach ( $files as $filepath ) {
	$filename = basename( $filepath, '.md' );
	$progress->tick();

	// Parse the file
	$parsed = scolta_parse_markdown_file( $filepath );
	$fm     = $parsed['frontmatter'];
	$body   = $parsed['body'];

	if ( empty( $fm ) || empty( $body ) ) {
		WP_CLI::warning( "Skipping $filename: empty frontmatter or body" );
		$skipped++;
		continue;
	}

	$title = $fm['title'] ?? $filename;
	$slug  = $fm['slug'] ?? sanitize_title( $title );

	// Check for existing post with this slug
	$existing = get_page_by_path( $slug, OBJECT, 'recipe' );
	if ( $existing ) {
		$skipped++;
		continue;
	}

	// Strip leading h1 if it matches the post title — WordPress renders the title separately.
	// The markdown files include a # Title heading for static site generators that need it.
	$body = preg_replace( '/^#\s+' . preg_quote( $title, '/' ) . '\s*\n+/i', '', $body );

	// Convert markdown body to HTML
	$html_content = scolta_markdown_to_html( $body );

	if ( $dry_run ) {
		$term_count = 0;
		foreach ( $taxonomy_map as $fm_key => $tax_slug ) {
			$values = $fm[ $fm_key ] ?? array();
			if ( is_string( $values ) && $values !== '' ) {
				$term_count++;
			} elseif ( is_array( $values ) ) {
				$term_count += count( $values );
			}
		}
		WP_CLI::log( "  [DRY RUN] Would create: $title ($slug) with $term_count taxonomy terms" );
		$created++;
		continue;
	}

	// Create the post
	// Use the source date from frontmatter as the publish date, if available.
	$post_data = array(
		'post_type'    => 'recipe',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $html_content,
		'post_status'  => 'publish',
		'post_author'  => 1,
	);

	if ( ! empty( $fm['date'] ) ) {
		$post_data['post_date']     = $fm['date'] . ' 00:00:00';
		$post_data['post_date_gmt'] = $fm['date'] . ' 00:00:00';
	}

	$post_id = wp_insert_post( $post_data, true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "Failed to create '$title': " . $post_id->get_error_message() );
		$errors++;
		continue;
	}

	// Set post meta
	if ( ! empty( $fm['source_url'] ) ) {
		update_post_meta( $post_id, 'source_url', esc_url_raw( $fm['source_url'] ) );
	}
	$license = $fm['license'] ?? 'CC-BY-SA 3.0';
	update_post_meta( $post_id, 'license', sanitize_text_field( $license ) );

	// Assign taxonomy terms
	foreach ( $taxonomy_map as $fm_key => $tax_slug ) {
		$values = $fm[ $fm_key ] ?? array();

		// Normalize: string → array
		if ( is_string( $values ) && $values !== '' ) {
			$values = array( $values );
		}
		if ( ! is_array( $values ) || empty( $values ) ) {
			continue;
		}

		$term_ids = array();
		foreach ( $values as $term_name ) {
			$term_name = trim( $term_name );
			if ( $term_name === '' ) {
				continue;
			}

			// Find or create the term
			$term = get_term_by( 'name', $term_name, $tax_slug );
			if ( $term ) {
				$term_ids[] = (int) $term->term_id;
			} else {
				$new_term = wp_insert_term( $term_name, $tax_slug );
				if ( ! is_wp_error( $new_term ) ) {
					$term_ids[] = (int) $new_term['term_id'];
				}
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $tax_slug );
		}
	}

	$created++;
}

$progress->finish();

WP_CLI::success( sprintf(
	'Import complete: %d created, %d skipped (duplicate), %d errors (of %d files)',
	$created,
	$skipped,
	$errors,
	count( $files )
) );
