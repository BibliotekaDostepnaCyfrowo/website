<?php
/**
 * Biblioteka Dostępna Cyfrowo theme.
 *
 * Content that staff need to change (opening hours, branches, partners,
 * footer links) lives in synced patterns, which Editors can edit without
 * access to the Site Editor. Templates pull them in by slug through the
 * [bdc_wzorzec] shortcode, so the theme files never depend on database IDs.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'biblioteka-dostepna-cyfrowo', get_stylesheet_uri(), array(), $version );
	}
);

/**
 * Montserrat is used for all text, so fetch it with the page instead of after
 * the CSS; otherwise text first renders in the fallback font and then reflows.
 */
add_action(
	'wp_head',
	function () {
		foreach ( array( 'latin', 'latin-ext' ) as $subset ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( "assets/fonts/montserrat-$subset-wght-normal.woff2" ) )
			);
		}
	},
	1
);

/**
 * Favicon: the "O" from the logo, kept in assets/icons so it ships with the
 * theme instead of living in the database. The SVG turns light in dark mode;
 * the .ico is for older browsers and plain /favicon.ico requests. A Site Icon
 * set in the admin takes over from these.
 */
function bdc_favicon_links() {
	if ( has_site_icon() ) {
		return;
	}
	$version = wp_get_theme()->get( 'Version' );
	$icon    = fn( $file ) => esc_url( add_query_arg( 'ver', $version, get_theme_file_uri( "assets/icons/$file" ) ) );
	printf( '<link rel="icon" href="%s" sizes="48x48">' . "\n", $icon( 'favicon.ico' ) );
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", $icon( 'favicon.svg' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", $icon( 'apple-touch-icon.png' ) );
}
add_action( 'wp_head', 'bdc_favicon_links' );
add_action( 'admin_head', 'bdc_favicon_links' );
add_action( 'login_head', 'bdc_favicon_links' );

// Without a Site Icon, core answers /favicon.ico with the WordPress logo.
add_action(
	'do_favicon',
	function () {
		if ( ! has_site_icon() ) {
			wp_safe_redirect( get_theme_file_uri( 'assets/icons/favicon.ico' ) );
			exit;
		}
	},
	9
);

/**
 * The branches map (Leaflet, bundled in assets/vendor) loads only on pages that
 * show it. Block themes render the page before wp_head, so enqueuing here works.
 */
add_filter(
	'render_block_core/html',
	function ( $content ) {
		if ( str_contains( $content, 'data-branches-map' ) ) {
			$version = wp_get_theme()->get( 'Version' );
			wp_enqueue_style( 'leaflet', get_theme_file_uri( 'assets/vendor/leaflet/leaflet.css' ), array(), '1.9.4' );
			wp_enqueue_script( 'leaflet', get_theme_file_uri( 'assets/vendor/leaflet/leaflet.js' ), array(), '1.9.4', array( 'strategy' => 'defer' ) );
			wp_enqueue_script(
				'biblioteka-dostepna-cyfrowo-branches-map',
				get_theme_file_uri( 'assets/js/branches-map.js' ),
				array( 'leaflet' ),
				$version,
				array( 'strategy' => 'defer' )
			);
		}
		return $content;
	}
);

/**
 * Grid / list switch on the news list. The visitor's choice is applied in <head>,
 * before the first paint, so cached pages don't flash the grid first; the
 * buttons themselves (assets/js/news-view.js) load only where the switch is.
 */
add_action(
	'wp_head',
	function () {
		wp_print_inline_script_tag(
			"try{if(localStorage.getItem('bdc-news-view')==='list')document.documentElement.setAttribute('data-bdc-news-view','list')}catch(e){}"
		);
	},
	1
);

add_filter(
	'render_block_core/html',
	function ( $content ) {
		if ( str_contains( $content, 'data-bdc-view-switch' ) ) {
			wp_enqueue_script(
				'biblioteka-dostepna-cyfrowo-news-view',
				get_theme_file_uri( 'assets/js/news-view.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				array( 'strategy' => 'defer' )
			);
		}
		return $content;
	}
);

/**
 * "?s=otwarcie+" searches for "otwarcie", not "otwarcie " (the title and the
 * field would show the stray space).
 */
add_filter(
	'request',
	function ( $query_vars ) {
		if ( isset( $query_vars['s'] ) && is_string( $query_vars['s'] ) ) {
			$query_vars['s'] = trim( $query_vars['s'] );
		}
		return $query_vars;
	}
);

/**
 * [bdc_liczba_wynikow] on the search page: "2 wyniki dla „otwarcie”".
 * Nothing when there are no results; the template has its own message then.
 */
add_shortcode(
	'bdc_liczba_wynikow',
	function () {
		global $wp_query;
		$count = (int) $wp_query->found_posts;
		if ( ! is_search() || 0 === $count ) {
			return '';
		}

		$tens = $count % 100;
		$ones = $count % 10;
		if ( 1 === $count ) {
			$noun = 'wynik';
		} elseif ( $ones >= 2 && $ones <= 4 && ( $tens < 12 || $tens > 14 ) ) {
			$noun = 'wyniki';
		} else {
			$noun = 'wyników';
		}

		return sprintf(
			'<p class="bdc-results-count">%s %s dla „<strong>%s</strong>”</p>',
			number_format_i18n( $count ),
			$noun,
			get_search_query()
		);
	}
);

/**
 * [bdc_wzorzec slug="godziny-otwarcia"] renders the synced pattern with that slug.
 */
add_shortcode(
	'bdc_wzorzec',
	function ( $atts ) {
		static $rendering = array();

		$slug = sanitize_title( $atts['slug'] ?? '' );
		if ( '' === $slug || isset( $rendering[ $slug ] ) ) {
			return '';
		}

		$pattern = get_page_by_path( $slug, OBJECT, 'wp_block' );
		if ( ! $pattern || 'publish' !== $pattern->post_status ) {
			return '';
		}

		$rendering[ $slug ] = true;
		$html               = do_blocks( $pattern->post_content );
		unset( $rendering[ $slug ] );

		return $html;
	}
);

/**
 * Editors can edit synced patterns, but WordPress hides the list from them,
 * so give it a plainly named menu entry.
 */
add_action(
	'admin_menu',
	function () {
		add_menu_page(
			'Stałe treści',
			'Stałe treści',
			'edit_posts',
			'edit.php?post_type=wp_block',
			'',
			'dashicons-clock',
			21
		);
	}
);

/*
 * Opening hours. Staff edit one table, the synced pattern "Godziny otwarcia"
 * (first column: Polish day name), which every branch in "Filie" shows. A branch
 * with different hours gets its own table instead (detach the pattern in that
 * branch and edit it). Today's hours are worked out here, when the page is
 * rendered, so they show without JavaScript and the page doesn't change after
 * loading. Pages are rendered fresh on every request; a page cache added later
 * must not keep pages across midnight.
 */

/**
 * Today's weekday in Polish, in the site's time zone (Europe/Warsaw).
 */
function bdc_today() {
	$days = array( 'niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota' );
	return $days[ (int) wp_date( 'w' ) ];
}

/**
 * The cells of one table row as plain text; the first is lowercased and loses a trailing colon.
 */
function bdc_row_cells( $row ) {
	preg_match_all( '#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $row, $matches );
	$cells = array_map(
		fn ( $cell ) => trim( html_entity_decode( wp_strip_all_tags( $cell ), ENT_QUOTES, 'UTF-8' ) ),
		$matches[1]
	);
	if ( $cells ) {
		$cells[0] = mb_strtolower( preg_replace( '/[\s:]+$/u', '', $cells[0] ) );
	}
	return $cells;
}

/**
 * Today's hours from the first opening-hours table in $html, or null when it has no row for today.
 */
function bdc_hours_today( $html ) {
	preg_match_all( '#<tr\b[^>]*>.*?</tr>#is', $html, $rows );
	foreach ( $rows[0] as $row ) {
		$cells = bdc_row_cells( $row );
		if ( count( $cells ) >= 2 && bdc_today() === $cells[0] ) {
			return $cells[1];
		}
	}
	return null;
}

/**
 * Hours without a digit ("dzień prac wewnętrznych") or no row at all mean closed.
 */
function bdc_is_open( $hours ) {
	return null !== $hours && preg_match( '/\d/', $hours );
}

/**
 * Today's hours as shown: the table's own words, or "nieczynne" when there are none.
 */
function bdc_hours_label( $hours ) {
	return null === $hours || '' === $hours ? 'nieczynne' : $hours;
}

/**
 * Marks today's row in every opening-hours table.
 */
add_filter(
	'render_block_core/table',
	function ( $content ) {
		if ( ! str_contains( $content, 'bdc-hours' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'#<tr\b[^>]*>.*?</tr>#is',
			function ( $match ) {
				$cells = bdc_row_cells( $match[0] );
				if ( ! $cells || bdc_today() !== $cells[0] ) {
					return $match[0];
				}
				$row = new WP_HTML_Tag_Processor( $match[0] );
				$row->next_tag( 'tr' );
				$row->add_class( 'is-today' );
				return $row->get_updated_html();
			},
			$content
		);
	}
);

/**
 * Shows today's hours above each branch's "Godziny otwarcia" toggle, read from
 * the table inside it, so nobody has to open the toggle to see them.
 */
add_filter(
	'render_block_core/details',
	function ( $content, $block ) {
		if ( ! str_contains( $block['attrs']['className'] ?? '', 'bdc-hours-toggle' ) ) {
			return $content;
		}
		$hours = bdc_hours_today( $content );
		return sprintf(
			'<p class="bdc-hours-today">Dziś (%s): <strong class="%s">%s</strong></p>',
			esc_html( bdc_today() ),
			bdc_is_open( $hours ) ? 'is-open' : 'is-closed',
			esc_html( bdc_hours_label( $hours ) )
		) . $content;
	},
	10,
	2
);

/**
 * Today's hours of every branch in the "Filie" pattern. A branch is a column or
 * card with an id (the map's pins link to it) whose first block is a heading.
 *
 * @return array<int, array{id: string, name: string, hours: ?string}>
 */
function bdc_branches_today() {
	static $branches = null;
	if ( null !== $branches ) {
		return $branches;
	}
	$branches = array();

	$pattern = get_page_by_path( 'filie', OBJECT, 'wp_block' );
	if ( ! $pattern || 'publish' !== $pattern->post_status ) {
		return $branches;
	}

	$walk = function ( $blocks ) use ( &$walk, &$branches ) {
		foreach ( $blocks as $block ) {
			$heading = $block['innerBlocks'][0] ?? null;
			if ( $heading && 'core/heading' === $heading['blockName'] && in_array( $block['blockName'], array( 'core/column', 'core/group' ), true ) ) {
				$tag = new WP_HTML_Tag_Processor( $block['innerHTML'] );
				$id  = $tag->next_tag() ? $tag->get_attribute( 'id' ) : null;
				if ( is_string( $id ) && '' !== $id ) {
					$branches[] = array(
						'id'    => $id,
						'name'  => trim( wp_strip_all_tags( $heading['innerHTML'] ) ),
						'hours' => bdc_hours_today( render_block( $block ) ),
					);
					continue;
				}
			}
			$walk( $block['innerBlocks'] );
		}
	};
	$walk( parse_blocks( $pattern->post_content ) );

	return $branches;
}

/**
 * [bdc_dzisiaj]: the "Dziś jesteśmy otwarci" bar, with today's hours of every
 * branch, each linking to its section further down the page.
 */
add_shortcode(
	'bdc_dzisiaj',
	function () {
		$branches = bdc_branches_today();
		$open     = array_filter( $branches, fn ( $branch ) => bdc_is_open( $branch['hours'] ) );

		if ( ! $branches ) {
			return '<p class="bdc-today__label has-large-font-size">Godziny otwarcia wszystkich filii:</p><p><a class="bdc-today__value" href="/kontakt/">zobacz godziny otwarcia</a></p>';
		}
		if ( ! $open ) {
			// "Dziś (środa) wszystkie filie są nieczynne (dzień prac wewnętrznych)."
			$reasons = array_unique( array_map( fn ( $branch ) => bdc_hours_label( $branch['hours'] ), $branches ) );
			$reason  = 1 === count( $reasons ) && 'nieczynne' !== $reasons[0] ? ' (' . $reasons[0] . ')' : '';
			return sprintf(
				'<p class="bdc-today__label has-large-font-size">Dziś (%s) wszystkie filie są nieczynne%s.</p><p><a href="#filie">Godziny otwarcia filii</a></p>',
				esc_html( bdc_today() ),
				esc_html( $reason )
			);
		}

		$items = '';
		foreach ( $branches as $branch ) {
			$is_open = bdc_is_open( $branch['hours'] );
			$items  .= sprintf(
				'<li><a href="#%s">%s: <strong class="%s">%s</strong></a></li>',
				esc_attr( $branch['id'] ),
				esc_html( $branch['name'] ),
				$is_open ? 'is-open' : 'is-closed',
				esc_html( bdc_hours_label( $branch['hours'] ) )
			);
		}
		return sprintf(
			'<p class="bdc-today__label has-large-font-size">Dziś (%s) jesteśmy otwarci:</p><ul class="bdc-today__list">%s</ul>',
			esc_html( bdc_today() ),
			$items
		);
	}
);
