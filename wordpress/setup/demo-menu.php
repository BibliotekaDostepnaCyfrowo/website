<?php
/**
 * Example deep menu for demo and staging sites, safe to re-run:
 * `wp eval-file /setup/demo-menu.php` (after setup.php).
 * `wp eval-file /setup/demo-menu.php usun` removes everything it created.
 *
 * Not part of a library's setup: it adds example sub-pages and a saved
 * three-level menu (a wp_navigation post named `bdc-przykladowe-menu`), so the
 * wide menu (default look) and the side index (BDC Katalog) have something to
 * show. The site's own menu is not changed: outside production the theme shows
 * the example menu instead only after ?menu=rozbudowane (see functions.php).
 * Everything created is marked with the `_bdc_demo_menu` meta and the removal
 * touches nothing else.
 */

defined( 'ABSPATH' ) || exit;

const BDC_DEMO_META = '_bdc_demo_menu';

function bdc_demo_created( $post_type ) {
	return get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => BDC_DEMO_META,
			'fields'         => 'ids',
		)
	);
}

if ( isset( $args[0] ) && 'usun' === $args[0] ) {
	foreach ( bdc_demo_created( 'wp_navigation' ) as $id ) {
		wp_delete_post( $id, true );
		WP_CLI::log( 'Removed the example menu' );
	}
	$pages = bdc_demo_created( 'page' );
	foreach ( $pages as $id ) {
		wp_delete_post( $id, true );
	}
	WP_CLI::success( 'Removed ' . count( $pages ) . ' demo pages' );
	return;
}

// Pages: slug => [title, children]. Existing pages are reused, never changed.
$tree = array(
	'nasza-biblioteka' => array(
		'Nasza biblioteka',
		array(
			'historia'  => array( 'Historia', array() ),
			'patron'    => array( 'Patron biblioteki', array() ),
			'zespol'    => array( 'Zespół', array() ),
			'dokumenty' => array(
				'Dokumenty',
				array(
					'statut'       => array( 'Statut', array() ),
					'sprawozdania' => array( 'Sprawozdania', array() ),
					'rodo'         => array( 'Ochrona danych osobowych', array() ),
				),
			),
		),
	),
	'dla-czytelnikow'  => array(
		'Dla czytelników',
		array(
			'zapisy'             => array( 'Zapisy i karta biblioteczna', array() ),
			'regulamin'          => array( 'Regulamin', array() ),
			'ksiazka-na-telefon' => array( 'Książka na telefon', array() ),
			'e-booki'            => array( 'E-booki i audiobooki', array() ),
			'czasopisma'         => array( 'Czasopisma', array() ),
		),
	),
	'wydarzenia'       => array(
		'Wydarzenia',
		array(
			'dkk'          => array( 'Dyskusyjny Klub Książki', array() ),
			'klub-malucha' => array( 'Klub malucha', array() ),
			'spotkania'    => array( 'Spotkania autorskie', array() ),
			'galeria'      => array(
				'Galeria',
				array(
					'galeria-2026' => array( 'Galeria 2026', array() ),
					'galeria-2025' => array( 'Galeria 2025', array() ),
					'galeria-2024' => array( 'Galeria 2024', array() ),
				),
			),
		),
	),
);

$urls = array();

function bdc_demo_pages( $tree, $parent, &$urls ) {
	foreach ( $tree as $slug => list( $title, $children ) ) {
		$path = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
		$page = get_page_by_path( $path );
		if ( $page ) {
			$id = $page->ID;
		} else {
			WP_CLI::log( "Creating page /$path/" );
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_parent'  => $parent,
					'post_content' => "<!-- wp:paragraph -->\n<p>Przykładowa strona „{$title}”. Treść uzupełnia biblioteka.</p>\n<!-- /wp:paragraph -->",
					'meta_input'   => array( BDC_DEMO_META => 1 ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( $id );
			}
		}
		$urls[ $slug ] = '/' . get_page_uri( $id ) . '/';
		bdc_demo_pages( $children, $id, $urls );
	}
}

bdc_demo_pages( $tree, 0, $urls );

function bdc_demo_link( $label, $url ) {
	return '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $label, 'url' => $url, 'kind' => 'custom' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " /-->\n";
}

function bdc_demo_submenu( $label, $url, $inner, $class = '' ) {
	$attrs = array( 'label' => $label );
	if ( $url ) {
		$attrs['url'] = $url;
	}
	$attrs['kind'] = 'custom';
	if ( $class ) {
		$attrs['className'] = $class;
	}
	return '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " -->\n" . $inner . "<!-- /wp:navigation-submenu -->\n";
}

$u = $urls;

// Top level: plain links and panels. A submenu inside a panel is a column with
// a heading; links placed directly in a panel sit in a row (Placówki).
$menu = bdc_demo_link( 'Aktualności', '/aktualnosci/' )
	. bdc_demo_submenu(
		'Biblioteka',
		'',
		bdc_demo_submenu( 'O bibliotece', $u['nasza-biblioteka'], bdc_demo_link( 'Historia', $u['historia'] ) . bdc_demo_link( 'Patron biblioteki', $u['patron'] ) . bdc_demo_link( 'Zespół', $u['zespol'] ) )
		. bdc_demo_submenu( 'Dokumenty', $u['dokumenty'], bdc_demo_link( 'Statut', $u['statut'] ) . bdc_demo_link( 'Sprawozdania', $u['sprawozdania'] ) . bdc_demo_link( 'Ochrona danych osobowych', $u['rodo'] ) . bdc_demo_link( 'Deklaracja dostępności', '/deklaracja-dostepnosci/' ) )
	)
	. bdc_demo_submenu(
		'Dla czytelników',
		'',
		bdc_demo_submenu( 'Korzystanie', $u['dla-czytelnikow'], bdc_demo_link( 'Zapisy i karta biblioteczna', $u['zapisy'] ) . bdc_demo_link( 'Regulamin', $u['regulamin'] ) . bdc_demo_link( 'Książka na telefon', $u['ksiazka-na-telefon'] ) )
		. bdc_demo_submenu( 'Zbiory', '', bdc_demo_link( 'Katalog online', '/katalog-online/' ) . bdc_demo_link( 'E-booki i audiobooki', $u['e-booki'] ) . bdc_demo_link( 'Czasopisma', $u['czasopisma'] ) )
	)
	. bdc_demo_submenu(
		'Wydarzenia',
		'',
		bdc_demo_submenu( 'Kluby i spotkania', $u['wydarzenia'], bdc_demo_link( 'Dyskusyjny Klub Książki', $u['dkk'] ) . bdc_demo_link( 'Klub malucha', $u['klub-malucha'] ) . bdc_demo_link( 'Spotkania autorskie', $u['spotkania'] ) )
		. bdc_demo_submenu( 'Galeria', $u['galeria'], bdc_demo_link( '2026', $u['galeria-2026'] ) . bdc_demo_link( '2025', $u['galeria-2025'] ) . bdc_demo_link( '2024', $u['galeria-2024'] ) . bdc_demo_link( 'Wszystkie lata', $u['galeria'] ), 'bdc-menu-chips' )
	)
	. bdc_demo_submenu(
		'Placówki',
		'',
		bdc_demo_link( 'Biblioteka główna', '/kontakt/#glowna' ) . bdc_demo_link( 'Filia Północ', '/kontakt/#polnoc' ) . bdc_demo_link( 'Filia Południe', '/kontakt/#poludnie' )
	)
	. bdc_demo_link( 'Kontakt', '/kontakt/' );

$existing = get_page_by_path( 'bdc-przykladowe-menu', OBJECT, 'wp_navigation' );
if ( $existing ) {
	wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $menu ) );
	WP_CLI::success( 'Updated the example menu; open any page with ?menu=rozbudowane' );
	return;
}

$id = wp_insert_post(
	array(
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_name'    => 'bdc-przykladowe-menu',
		'post_title'   => 'Przykładowe menu (podgląd)',
		'post_content' => $menu,
		'meta_input'   => array( BDC_DEMO_META => 1 ),
	),
	true
);
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id );
}
WP_CLI::success( 'Added the example menu; open any page with ?menu=rozbudowane' );
