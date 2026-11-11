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
 * Favicon: the Biblioteka Dostępna Cyfrowo programme icon, as on
 * https://dostepnacyfrowo.pl/. The .ico (16–64px) and 180px touch icon are that
 * site's files; favicon.svg is the vector logo on a white tile, so it stays
 * legible on dark tab bars. Kept in assets/icons so it ships with the theme
 * instead of living in the database; the .ico also answers plain /favicon.ico
 * requests. A Site Icon set in the admin takes over from these.
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

/*
 * BDC Katalog: the print-inspired look (styles/katalog.json + assets/css/katalog.css).
 *
 * It is on when an admin picks the "BDC Katalog" style in the Site Editor, or, outside
 * production only, as a preview for one visitor: ?styl=katalog (Daylight, and Reading room
 * when the device is set to dark mode), ?styl=katalog-kontrast (High contrast), and
 * ?styl=domyslny to go back. The choice is kept in a cookie while clicking around.
 * Production never reads the parameter or the cookie, so a cached public page can never
 * carry someone's preview.
 */

const BDC_STYLE_COOKIE = 'bdc_styl';

function bdc_katalog_preview_allowed() {
	return 'production' !== wp_get_environment_type();
}

/** '' (default look), 'katalog' or 'katalog-kontrast'. */
function bdc_katalog_mode() {
	static $mode = null;
	if ( null !== $mode ) {
		return $mode;
	}
	$mode    = '';
	$allowed = array( 'katalog', 'katalog-kontrast' );
	if ( bdc_katalog_preview_allowed() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only display preference.
		$wanted = isset( $_GET['styl'] ) ? sanitize_key( wp_unslash( $_GET['styl'] ) ) : null;
		if ( null === $wanted && isset( $_COOKIE[ BDC_STYLE_COOKIE ] ) ) {
			$wanted = sanitize_key( wp_unslash( $_COOKIE[ BDC_STYLE_COOKIE ] ) );
		}
		if ( in_array( $wanted, $allowed, true ) ) {
			$mode = $wanted;
			return $mode;
		}
		if ( 'domyslny' === $wanted ) {
			return $mode;
		}
	}
	if ( 'katalog' === wp_get_global_settings( array( 'custom', 'bdcStyle' ) ) ) {
		$mode = 'katalog';
	}
	return $mode;
}

/** Whether this request previews Katalog without it being the chosen site style. */
function bdc_katalog_is_preview() {
	return '' !== bdc_katalog_mode() && 'katalog' !== wp_get_global_settings( array( 'custom', 'bdcStyle' ) );
}

// Remember (or forget) a preview chosen with ?styl=.
add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! bdc_katalog_preview_allowed() || ! isset( $_GET['styl'] ) || headers_sent() ) {
			return;
		}
		$wanted = sanitize_key( wp_unslash( $_GET['styl'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$opts   = array(
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( in_array( $wanted, array( 'katalog', 'katalog-kontrast' ), true ) ) {
			setcookie( BDC_STYLE_COOKIE, $wanted, array( 'expires' => time() + 30 * DAY_IN_SECONDS ) + $opts );
		} elseif ( 'domyslny' === $wanted ) {
			setcookie( BDC_STYLE_COOKIE, '', array( 'expires' => time() - HOUR_IN_SECONDS ) + $opts );
		}
		nocache_headers();
	}
);

// A preview merges the variation over the theme's own theme.json for this request only.
add_filter(
	'wp_theme_json_data_theme',
	function ( $theme_json ) {
		if ( is_admin() || ! bdc_katalog_preview_allowed() ) {
			return $theme_json;
		}
		// Read the preference directly: global settings aren't available while theme.json is being built.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wanted = isset( $_GET['styl'] ) ? sanitize_key( wp_unslash( $_GET['styl'] ) ) : ( isset( $_COOKIE[ BDC_STYLE_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ BDC_STYLE_COOKIE ] ) ) : '' );
		if ( ! in_array( $wanted, array( 'katalog', 'katalog-kontrast' ), true ) ) {
			return $theme_json;
		}
		$file = get_theme_file_path( 'styles/katalog.json' );
		if ( ! is_readable( $file ) ) {
			return $theme_json;
		}
		$variation = wp_json_file_decode( $file, array( 'associative' => true ) );
		unset( $variation['title'], $variation['$schema'] );
		return $theme_json->update_with( $variation );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( '' === bdc_katalog_mode() ) {
			return;
		}
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'bdc-katalog', get_theme_file_uri( 'assets/css/katalog.css' ), array( 'biblioteka-dostepna-cyfrowo' ), $version );
	},
	20
);

add_filter(
	'body_class',
	function ( $classes ) {
		$mode = bdc_katalog_mode();
		if ( '' !== $mode ) {
			$classes[] = 'bdc-katalog';
			if ( 'katalog-kontrast' === $mode ) {
				$classes[] = 'bdc-katalog--kontrast';
			}
		}
		return $classes;
	}
);

// Katalog uses its Caslon wordmark: dark ink on paper, light ink on the footer and in dark mode.
add_filter(
	'render_block_core/site-logo',
	function ( $content ) {
		if ( '' === bdc_katalog_mode() || ! preg_match( '/<img\b[^>]*>/', $content, $img ) ) {
			return $content;
		}
		$alt  = preg_match( '/\balt="([^"]*)"/', $img[0], $m ) ? $m[1] : esc_attr( get_bloginfo( 'name' ) );
		$logo = sprintf(
			'<img class="bdc-katalog-logo bdc-katalog-logo--dark" src="%1$s" alt="%3$s" width="226" height="44"><img class="bdc-katalog-logo bdc-katalog-logo--light" src="%2$s" alt="%3$s" width="226" height="44">',
			esc_url( get_theme_file_uri( 'assets/images/katalog-logo.svg' ) ),
			esc_url( get_theme_file_uri( 'assets/images/katalog-logo-light.svg' ) ),
			$alt
		);
		return str_replace( $img[0], $logo, $content );
	}
);

// Staging only: a small bar to switch between the looks while previewing.
add_action(
	'wp_footer',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! bdc_katalog_preview_allowed() || ( ! isset( $_GET['styl'] ) && ! isset( $_COOKIE[ BDC_STYLE_COOKIE ] ) && ! isset( $_GET['menu'] ) && ! isset( $_COOKIE[ BDC_MENU_COOKIE ] ) ) ) {
			return;
		}
		$mode    = bdc_katalog_mode();
		$current = '' === $mode ? 'domyslny' : $mode;
		$options = array(
			'domyslny'         => 'Domyślny',
			'katalog'          => 'Katalog',
			'katalog-kontrast' => 'Katalog: wysoki kontrast',
		);
		echo '<style>.bdc-style-switcher{position:fixed;right:max(16px,env(safe-area-inset-right,0px));bottom:max(16px,env(safe-area-inset-bottom,0px));z-index:1000;display:flex;flex-wrap:wrap;align-items:center;gap:.25rem .75rem;max-width:calc(100vw - 32px);padding:.5rem .75rem;border:1px solid #1d1e2c;border-radius:2px;background:#fffdf7;color:#1d1e2c;font:400 .8125rem/1.4 "Courier Prime","Courier New",monospace;box-shadow:0 2px 0 0 #1d1e2c}.bdc-style-switcher a{display:inline-flex;align-items:center;min-height:24px;color:#1f4e8c;text-underline-offset:3px}.bdc-style-switcher a:focus-visible{outline:2px solid #1f4e8c;outline-offset:2px}.bdc-style-switcher [aria-current]{color:#1d1e2c;font-weight:700;text-decoration:none}</style>';
		echo '<nav class="bdc-style-switcher" aria-label="Podgląd wyglądu strony"><span>Podgląd:</span>';
		foreach ( $options as $key => $label ) {
			printf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( 'styl', $key ) ),
				$key === $current ? ' aria-current="true"' : '',
				esc_html( $label )
			);
		}
		if ( bdc_demo_menu_post() ) {
			echo '<span>Menu:</span>';
			$menu = bdc_demo_menu_id() ? 'rozbudowane' : 'zwykle';
			foreach ( array( 'zwykle' => 'Zwykłe', 'rozbudowane' => 'Rozbudowane' ) as $key => $label ) {
				printf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( add_query_arg( 'menu', $key ) ),
					$key === $menu ? ' aria-current="true"' : '',
					esc_html( $label )
				);
			}
		}
		echo '</nav>';
	}
);

/**
 * Staging only: a preview of the example deep menu that setup/demo-menu.php saves
 * (a wp_navigation post named bdc-przykladowe-menu). ?menu=rozbudowane shows it in
 * place of the header's main menu for this visitor, ?menu=zwykle goes back; the
 * choice is kept in a cookie. The site's own menu is never changed, and
 * production ignores the parameter and the cookie.
 */
const BDC_MENU_COOKIE = 'bdc_menu';

function bdc_demo_menu_post() {
	static $post = false;
	if ( false === $post ) {
		$post = bdc_katalog_preview_allowed() ? get_page_by_path( 'bdc-przykladowe-menu', OBJECT, 'wp_navigation' ) : null;
		if ( $post && 'publish' !== $post->post_status ) {
			$post = null;
		}
	}
	return $post;
}

/** The example menu's ID when this visitor previews it, else 0. */
function bdc_demo_menu_id() {
	if ( ! bdc_katalog_preview_allowed() ) {
		return 0;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only display preference.
	$wanted = isset( $_GET['menu'] ) ? sanitize_key( wp_unslash( $_GET['menu'] ) ) : ( isset( $_COOKIE[ BDC_MENU_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ BDC_MENU_COOKIE ] ) ) : '' );
	$post   = 'rozbudowane' === $wanted ? bdc_demo_menu_post() : null;
	return $post ? $post->ID : 0;
}

add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! bdc_katalog_preview_allowed() || ! isset( $_GET['menu'] ) || headers_sent() ) {
			return;
		}
		$wanted = sanitize_key( wp_unslash( $_GET['menu'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$opts   = array(
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( 'rozbudowane' === $wanted ) {
			setcookie( BDC_MENU_COOKIE, $wanted, array( 'expires' => time() + 30 * DAY_IN_SECONDS ) + $opts );
		} elseif ( 'zwykle' === $wanted ) {
			setcookie( BDC_MENU_COOKIE, '', array( 'expires' => time() - HOUR_IN_SECONDS ) + $opts );
		}
		nocache_headers();
	}
);

// The header's main menu renders the example menu instead of its own links. A
// nested block ignores attributes changed in render_block_data, so render a copy
// that points at the saved menu.
add_filter(
	'pre_render_block',
	function ( $pre, $block ) {
		if ( null !== $pre || 'core/navigation' !== $block['blockName'] || isset( $block['attrs']['ref'] ) || 'Menu główne' !== ( $block['attrs']['ariaLabel'] ?? '' ) ) {
			return $pre;
		}
		$id = bdc_demo_menu_id();
		if ( ! $id ) {
			return $pre;
		}
		$block['attrs']['ref'] = $id;
		$block['innerBlocks']  = array();
		$block['innerHTML']    = '';
		$block['innerContent'] = array();
		return render_block( $block );
	},
	10,
	2
);

/**
 * Main menu, third level. A first-level item opens a wide panel (style.css); a
 * submenu nested inside it is a group of that panel: a heading with its links
 * always shown, not a second toggle to open. Core renders every submenu as a
 * closed toggle, so the heads of the nested ones are rebuilt here. Each submenu
 * converts the submenus inside it; the innermost render first.
 */
add_filter(
	'render_block_core/navigation-submenu',
	function ( $content ) {
		bdc_enqueue_menu_script();
		$head  ='~<li\b[^>]*\bclass="([^"]*\bhas-child\b[^"]*)"[^>]*>((?:(?!<ul\b|<li\b).)*)<ul\b[^>]*\bwp-block-navigation__submenu-container\b[^>]*>~s';
		$first = true;
		return preg_replace_callback(
			$head,
			function ( $match ) use ( &$first ) {
				if ( $first ) {
					$first = false;
					return $match[0];
				}
				$classes = array_diff( preg_split( '/\s+/', trim( $match[1] ) ), array( 'has-child', 'open-on-click', 'open-on-hover-click', 'wp-block-navigation-submenu' ) );
				// With submenus opening on hover the head is a link plus an arrow button: keep the link.
				if ( preg_match( '~<a\b[^>]*>.*?</a>~s', $match[2], $link ) ) {
					$title = str_replace( 'wp-block-navigation-item__content', 'wp-block-navigation-item__content bdc-menu-group__title', $link[0] );
				} else {
					preg_match( '~<span class="wp-block-navigation-item__label">(.*?)</span>~s', $match[2], $label );
					$title = '<span class="bdc-menu-group__title">' . ( $label[1] ?? '' ) . '</span>';
				}
				return '<li class="' . esc_attr( implode( ' ', $classes ) ) . ' bdc-menu-group">' . $title . '<ul class="bdc-menu-group__list">';
			},
			$content
		) ?? $content;
	}
);

/** The script that opens and closes the sections of the hamburger menu and of the side index. */
function bdc_enqueue_menu_script() {
	wp_enqueue_script(
		'biblioteka-dostepna-cyfrowo-menu-accordion',
		get_theme_file_uri( 'assets/js/menu-accordion.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		array( 'strategy' => 'defer' )
	);
}

/**
 * Whether a menu link points at the address being viewed. Core marks links to
 * pages and posts itself, but not custom links, which the theme's menus use.
 */
function bdc_is_current_url( $url ) {
	$link = wp_parse_url( $url );
	if ( ! $link || is_search() || ( ! isset( $link['path'] ) && ! isset( $link['host'] ) ) ) {
		return false; // An empty or "#…" link, or search results (their address is the home page's).
	}
	if ( isset( $link['host'] ) && wp_parse_url( home_url(), PHP_URL_HOST ) !== $link['host'] ) {
		return false;
	}
	$here = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	if ( untrailingslashit( $link['path'] ?? '' ) !== untrailingslashit( $here['path'] ?? '' ) ) {
		return false;
	}
	if ( isset( $link['query'] ) ) {
		return ( $here['query'] ?? '' ) === $link['query'];
	}
	// Without pretty permalinks every page shares the home page's path.
	return get_option( 'permalink_structure' ) || is_front_page();
}

add_filter(
	'render_block_core/navigation-link',
	function ( $content, $block ) {
		if ( str_contains( $content, 'aria-current' ) || ! bdc_is_current_url( $block['attrs']['url'] ?? '' ) ) {
			return $content;
		}
		return preg_replace( '/<a\b/', '<a aria-current="page"', $content, 1 );
	},
	10,
	2
);

/*
 * BDC Katalog, a menu with submenus: a "Spis treści" side index.
 *
 * Katalog has no wide panels. On wide screens the header shows no menu bar; the
 * same menu stands beside the page content as a numbered index whose sections
 * open in place (assets/css/katalog.css lays it out, assets/js/menu-accordion.js
 * opens the sections). It is built from the header menu as rendered, so staff
 * edit one menu. Narrower screens keep the hamburger menu and never see the index.
 */

/** The index markup for this request ('' when there is none); pass a string to set it. */
function bdc_index_html( $set = null ) {
	static $html = '';
	if ( null !== $set ) {
		$html = $set;
	}
	return $html;
}

/** Whether the index was placed beside <main> on this request. */
function bdc_index_placed( $set = null ) {
	static $placed = false;
	if ( null !== $set ) {
		$placed = $set;
	}
	return $placed;
}

/** Menu items of one rendered list: label, url, current, children. */
function bdc_index_items( DOMElement $list ) {
	$items = array();
	foreach ( $list->childNodes as $li ) {
		if ( ! $li instanceof DOMElement || 'li' !== $li->tagName ) {
			continue;
		}
		$item = array(
			'label'    => '',
			'url'      => '',
			'current'  => false,
			'children' => array(),
		);
		foreach ( $li->childNodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			if ( 'ul' === $node->tagName ) {
				$item['children'] = bdc_index_items( $node );
			} elseif ( '' === $item['label'] ) {
				$item['label'] = trim( $node->textContent );
				if ( 'a' === $node->tagName && $node->getAttribute( 'href' ) ) {
					$item['url']     = $node->getAttribute( 'href' );
					$item['current'] = 'page' === $node->getAttribute( 'aria-current' );
				}
			}
		}
		if ( '' !== $item['label'] ) {
			$items[] = $item;
		}
	}
	return $items;
}

function bdc_index_has_current( $items ) {
	foreach ( $items as $item ) {
		if ( $item['current'] || bdc_index_has_current( $item['children'] ) ) {
			return true;
		}
	}
	return false;
}

/** The links of an open section; an item with children is a heading over its own list. */
function bdc_index_list( $items, $attrs ) {
	$out = '<ul ' . $attrs . '>';
	foreach ( $items as $item ) {
		$out .= '<li>';
		if ( '' !== $item['url'] ) {
			$out .= sprintf( '<a href="%s"%s>%s</a>', esc_url( $item['url'] ), $item['current'] ? ' aria-current="page"' : '', esc_html( $item['label'] ) );
		} else {
			$out .= '<span class="bdc-index__group">' . esc_html( $item['label'] ) . '</span>';
		}
		if ( $item['children'] ) {
			$out .= bdc_index_list( $item['children'], 'class="bdc-index__sublist"' );
		}
		$out .= '</li>';
	}
	return $out . '</ul>';
}

// The header menu renders first: keep it as the index when it has submenus.
add_filter(
	'render_block_core/navigation',
	function ( $content ) {
		if ( '' === bdc_katalog_mode() || '' !== bdc_index_html() || ! str_contains( $content, 'has-child' ) || ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $content );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$list = ( new DOMXPath( $dom ) )->query( '//ul[contains(concat(" ", normalize-space(@class), " "), " wp-block-navigation__container ")]' )->item( 0 );
		if ( ! $list instanceof DOMElement ) {
			return $content;
		}
		$sections = '';
		foreach ( bdc_index_items( $list ) as $i => $item ) {
			$no   = $i + 1;
			$head = '<span class="bdc-index__no" aria-hidden="true">' . $no . '</span><span class="bdc-index__name">' . esc_html( $item['label'] ) . '</span>';
			if ( $item['children'] ) {
				// Closed sections are hidden only where scripts run (katalog.css): without them everything is listed.
				$sections .= sprintf(
					'<li><button type="button" class="bdc-index__head" aria-expanded="%s" aria-controls="bdc-index-%d">%s</button>%s</li>',
					bdc_index_has_current( $item['children'] ) ? 'true' : 'false',
					$no,
					$head,
					bdc_index_list( $item['children'], 'id="bdc-index-' . $no . '" class="bdc-index__list"' )
				);
			} elseif ( '' !== $item['url'] ) {
				$sections .= sprintf( '<li><a class="bdc-index__head" href="%s"%s>%s</a></li>', esc_url( $item['url'] ), $item['current'] ? ' aria-current="page"' : '', $head );
			}
		}
		bdc_enqueue_menu_script();
		bdc_index_html( '<nav class="bdc-index" aria-label="Spis treści"><p class="bdc-index__label">Spis treści</p><ol class="bdc-index__sections">' . $sections . '</ol></nav>' );
		return $content;
	}
);

// The index stands beside the template's <main> (not one inside the page's own content).
add_filter(
	'render_block_core/group',
	function ( $content, $block ) {
		if ( 'main' !== ( $block['attrs']['tagName'] ?? '' ) || '' === bdc_index_html() || bdc_index_placed() || doing_filter( 'the_content' ) ) {
			return $content;
		}
		bdc_index_placed( true );
		return '<div class="bdc-index-layout">' . bdc_index_html() . $content . '</div>';
	},
	10,
	2
);

// The template is rendered before <body> is printed, so the class knows about the index.
add_filter(
	'body_class',
	function ( $classes ) {
		if ( bdc_index_placed() ) {
			$classes[] = 'bdc-has-index';
		}
		return $classes;
	}
);
