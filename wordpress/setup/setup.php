<?php
/**
 * One-off site setup, safe to re-run: `wp eval-file /setup/setup.php`.
 *
 * Applies the settings and starter content the theme expects. Existing
 * synced patterns are never overwritten, so edits made by staff survive.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Example data: a fictional library. Every value here, and everything this script
// creates, is starter content that staff replace in the admin.
$library = 'Biblioteka Dostępna Cyfrowo';

$branches = array(
	array(
		'id'      => 'glowna',
		'name'    => 'Biblioteka główna',
		'street'  => 'ul. Przykładowa 1',
		'city'    => '00-001 Przykładowo',
		'phones'  => array( '+48 00 000 00 01' ),
		'emails'  => array( 'biblioteka@example.org' ),
		'catalog' => 'https://xn--szukamksiki-4kb16m.pl/',
		'lat'     => 52.0693,
		'lon'     => 19.4803,
		'is_main' => true,
	),
	array(
		'id'      => 'polnoc',
		'name'    => 'Filia Północ',
		'street'  => 'ul. Czytelników 12',
		'city'    => '00-002 Przykładowo',
		'phones'  => array( '+48 00 000 00 02' ),
		'emails'  => array( 'polnoc@example.org' ),
		'catalog' => 'https://xn--szukamksiki-4kb16m.pl/',
		'lat'     => 52.0921,
		'lon'     => 19.4655,
	),
	array(
		'id'      => 'poludnie',
		'name'    => 'Filia Południe',
		'street'  => 'ul. Książkowa 5',
		'city'    => '00-003 Przykładowo',
		'phones'  => array( '+48 00 000 00 03' ),
		'emails'  => array( 'poludnie@example.org' ),
		'catalog' => 'https://xn--szukamksiki-4kb16m.pl/',
		'lat'     => 52.0478,
		'lon'     => 19.5012,
	),
);

// Opening hours, shared by every branch: one row per day.
$hours = array(
	array( 'Poniedziałek', '10.00 – 18.00' ),
	array( 'Wtorek', '9.00 – 17.00' ),
	array( 'Środa', 'dzień prac wewnętrznych' ),
	array( 'Czwartek', '8.00 – 16.00' ),
	array( 'Piątek', '8.00 – 16.00' ),
);
$logo_url = '';

// A real library instead of the example: BDC_LIBRARY_JSON names a JSON file with the
// `library` object of a copy manifest (biblioteka-ops, /kopie; written by german-lab's
// scripts/biblioteka-copy.sh):
//   {"name", "logo_url"?, "hours": [{"day", "hours"}],
//    "branches": [{"id", "name", "street", "city", "phones", "emails", "catalog"?, "lat"?, "lon"?, "is_main"?}]}
// The main branch (is_main, else the first) comes first. Without the variable, the
// example library above is used.
$is_example = true;
$library_json = getenv( 'BDC_LIBRARY_JSON' );
if ( $library_json ) {
	if ( ! is_readable( $library_json ) ) {
		WP_CLI::error( "BDC_LIBRARY_JSON: cannot read $library_json" );
	}
	$data = json_decode( file_get_contents( $library_json ), true );
	if ( ! is_array( $data ) || empty( $data['name'] ) || empty( $data['branches'] ) || ! is_array( $data['branches'] ) ) {
		WP_CLI::error( 'BDC_LIBRARY_JSON: needs "name" and a non-empty "branches" list' );
	}
	$library  = (string) $data['name'];
	$logo_url = isset( $data['logo_url'] ) ? (string) $data['logo_url'] : '';
	$branches = array();
	foreach ( $data['branches'] as $i => $b ) {
		foreach ( array( 'id', 'name', 'street', 'city' ) as $field ) {
			if ( empty( $b[ $field ] ) ) {
				WP_CLI::error( "BDC_LIBRARY_JSON: branches[$i] has no \"$field\"" );
			}
		}
		$branches[] = array(
			'id'      => sanitize_title( $b['id'] ),
			'name'    => (string) $b['name'],
			'street'  => (string) $b['street'],
			'city'    => (string) $b['city'],
			'phones'  => array_values( array_map( 'strval', (array) ( $b['phones'] ?? array() ) ) ),
			'emails'  => array_values( array_map( 'strval', (array) ( $b['emails'] ?? array() ) ) ),
			'catalog' => isset( $b['catalog'] ) ? (string) $b['catalog'] : '',
			'lat'     => isset( $b['lat'], $b['lon'] ) ? (float) $b['lat'] : null,
			'lon'     => isset( $b['lat'], $b['lon'] ) ? (float) $b['lon'] : null,
			'is_main' => ! empty( $b['is_main'] ),
		);
	}
	// Main branch first; with none marked, the first one is the main one.
	usort( $branches, fn( $a, $b ) => (int) $b['is_main'] - (int) $a['is_main'] );
	$branches[0]['is_main'] = true;
	if ( ! empty( $data['hours'] ) && is_array( $data['hours'] ) ) {
		$hours = array();
		foreach ( $data['hours'] as $row ) {
			if ( ! empty( $row['day'] ) && isset( $row['hours'] ) ) {
				$hours[] = array( (string) $row['day'], (string) $row['hours'] );
			}
		}
	}
	$is_example = false;
	WP_CLI::log( "Library from $library_json: $library, " . count( $branches ) . ' branches' );
}

function bdc_log( $message ) {
	WP_CLI::log( $message );
}

function bdc_ensure_page( $slug, $title, $content = '' ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		return $page->ID;
	}
	bdc_log( "Creating page /$slug/" );
	return wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
}

function bdc_ensure_pattern( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug, OBJECT, 'wp_block' );
	if ( $existing ) {
		return $existing->ID;
	}
	bdc_log( "Creating synced pattern '$title'" );
	return wp_insert_post(
		array(
			'post_type'    => 'wp_block',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
}

function bdc_contact_links( $branch ) {
	$links = array();
	foreach ( $branch['phones'] as $phone ) {
		$links[] = sprintf( '<a href="tel:%s">%s</a>', preg_replace( '/[^+\d]/', '', $phone ), esc_html( $phone ) );
	}
	foreach ( $branch['emails'] as $email ) {
		$links[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_html( $email ) );
	}
	return implode( '<br>', $links );
}

/**
 * One map with a pin per branch. The list is the fallback without JavaScript;
 * assets/js/branches-map.js turns it into the map and fills each pin's popup
 * from the branch section the link points to.
 */
function bdc_branches_map( $branches ) {
	$items = '';
	foreach ( $branches as $branch ) {
		if ( ! isset( $branch['lat'], $branch['lon'] ) ) {
			continue;
		}
		$items .= sprintf(
			'<li data-lat="%F" data-lon="%F"%s><a href="#%s">%s</a></li>',
			$branch['lat'],
			$branch['lon'],
			empty( $branch['is_main'] ) ? '' : ' data-main',
			esc_attr( $branch['id'] ),
			esc_html( $branch['name'] )
		);
	}
	return "<!-- wp:html -->\n<div class=\"bdc-map\" data-branches-map><ul class=\"bdc-map__list\">$items</ul></div>\n<!-- /wp:html -->\n";
}

function bdc_branch_heading( $branch ) {
	return "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $branch['name'] ) . "</h3>\n<!-- /wp:heading -->\n";
}

// The branch's own catalog on szukamksiążki.pl.
function bdc_branch_catalog( $branch ) {
	if ( empty( $branch['catalog'] ) ) {
		return '';
	}
	return "<!-- wp:paragraph {\"className\":\"bdc-more\"} -->\n<p class=\"bdc-more\"><a href=\"" . esc_url( $branch['catalog'] ) . "\">Katalog online</a></p>\n<!-- /wp:paragraph -->\n";
}

function bdc_branch_details( $branch, $hours_ref ) {
	return "<!-- wp:paragraph -->\n<p>" . esc_html( $branch['street'] ) . '<br>' . esc_html( $branch['city'] ) . "</p>\n<!-- /wp:paragraph -->\n"
		. "<!-- wp:paragraph -->\n<p>" . bdc_contact_links( $branch ) . "</p>\n<!-- /wp:paragraph -->\n"
		. bdc_branch_catalog( $branch )
		. "<!-- wp:details {\"className\":\"bdc-hours-toggle\"} -->\n<details class=\"wp-block-details bdc-hours-toggle\"><summary>Godziny otwarcia</summary>"
		. "<!-- wp:block {\"ref\":$hours_ref} /-->"
		. "</details>\n<!-- /wp:details -->\n";
}

// --- Settings -----------------------------------------------------------------

update_option( 'blogname', $library );
update_option( 'blogdescription', '' );
update_option( 'timezone_string', 'Europe/Warsaw' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'H:i' );
update_option( 'start_of_week', 1 );
update_option( 'posts_per_page', 9 );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );

// The old theme ignored pinned ("sticky") posts, so some were left pinned for
// years. The new theme shows pinned posts first, so unpin anything stale.
foreach ( get_option( 'sticky_posts', array() ) as $sticky_id ) {
	$sticky = get_post( $sticky_id );
	if ( ! $sticky || strtotime( $sticky->post_date_gmt ) < strtotime( '-1 year' ) ) {
		unstick_post( $sticky_id );
		bdc_log( 'Unpinned old post: ' . ( $sticky ? $sticky->post_title : "#$sticky_id" ) );
	}
}

$front = bdc_ensure_page( 'strona-glowna', 'Strona główna' );
$about = bdc_ensure_page(
	'nasza-biblioteka',
	'Nasza biblioteka',
	'<!-- wp:paragraph -->
<p>Tu biblioteka przedstawia swoją historię, misję i zespół. To przykładowa treść: zmień ją w panelu, w Strony → Nasza biblioteka.</p>
<!-- /wp:paragraph -->'
);
$a11y  = bdc_ensure_page(
	'deklaracja-dostepnosci',
	'Deklaracja dostępności',
	'<!-- wp:paragraph -->
<p>Każdy podmiot publiczny publikuje deklarację dostępności swojej strony internetowej, zgodnie z ustawą z dnia 4 kwietnia 2019 r. o dostępności cyfrowej stron internetowych i aplikacji mobilnych podmiotów publicznych. To miejsce na jej treść.</p>
<!-- /wp:paragraph -->'
);
$news  = bdc_ensure_page( 'aktualnosci', 'Aktualności' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
update_option( 'page_for_posts', $news );

// Example news, only for the example library on a site without any posts, so the news
// list isn't empty. A real library's news come from its old site.
if ( $is_example && ! get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
	$news_items = array(
		array( 'Spotkanie autorskie w bibliotece głównej', 'Zapraszamy na spotkanie z autorką książek dla dzieci. Wstęp wolny, liczba miejsc ograniczona.' ),
		array( 'Nowości w księgozbiorze', 'Do naszych placówek trafiło ponad sto nowych tytułów: powieści, reportaże i książki dla najmłodszych.' ),
		array( 'Ferie z biblioteką', 'Przez dwa tygodnie ferii czekają na dzieci warsztaty plastyczne, gry planszowe i głośne czytanie.' ),
	);
	foreach ( $news_items as $i => list( $title, $text ) ) {
		bdc_log( "Creating example post '$title'" );
		wp_insert_post(
			array(
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->",
				'post_date'    => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 3 ) . ' days' ) ),
			)
		);
	}
}

// --- Logo ---------------------------------------------------------------------

if ( ! get_theme_mod( 'custom_logo' ) && $logo_url ) {
	$logo = media_sideload_image( $logo_url, 0, $library, 'id' );
	if ( is_wp_error( $logo ) ) {
		WP_CLI::warning( "Logo download from $logo_url failed: " . $logo->get_error_message() . '; using the theme\'s logo' );
	} else {
		update_post_meta( $logo, '_wp_attachment_image_alt', "$library – strona główna" );
		set_theme_mod( 'custom_logo', $logo );
		bdc_log( "Logo set from $logo_url" );
	}
}
if ( ! get_theme_mod( 'custom_logo' ) ) {
	$tmp = wp_tempnam( 'logo.png' );
	copy( get_theme_file_path( 'assets/images/logo.png' ), $tmp );
	$logo = media_handle_sideload(
		array(
			'name'     => 'logo.png',
			'tmp_name' => $tmp,
		),
		0,
		$library
	);
	if ( is_wp_error( $logo ) ) {
		WP_CLI::warning( 'Logo upload failed: ' . $logo->get_error_message() );
	} else {
		update_post_meta( $logo, '_wp_attachment_image_alt', "$library – strona główna" );
		set_theme_mod( 'custom_logo', $logo );
		bdc_log( 'Logo set' );
	}
}

// --- Synced patterns (editable by staff under "Stałe treści") ------------------

bdc_ensure_pattern(
	'powitanie',
	'Strona główna: powitanie',
	'<!-- wp:paragraph {"className":"bdc-kicker"} -->
<p class="bdc-kicker">' . ( $is_example ? 'Przykładowa strona biblioteki publicznej' : 'Biblioteka publiczna' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html( $library ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">' . ( $is_example
		? 'Wzór strony internetowej biblioteki, dostępnej dla każdego: zgodnej z WCAG 2.2, czytelnej na telefonie i prostej w prowadzeniu. Adresy, godziny i liczby na tej stronie są przykładowe.'
		: 'Zapraszamy do naszych placówek. Sprawdź godziny otwarcia, nowości i wydarzenia.' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#filie">Godziny i adresy</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/nasza-biblioteka/">O bibliotece</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->'
);

$branch_count = count( $branches );
$branch_word  = 1 === $branch_count ? 'placówka biblioteczna' : ( $branch_count % 10 >= 2 && $branch_count % 10 <= 4 && ( $branch_count % 100 < 12 || $branch_count % 100 > 14 ) ? 'placówki biblioteczne' : 'placówek bibliotecznych' );
if ( ! $is_example ) {
	bdc_ensure_pattern(
		'liczby',
		'Strona główna: biblioteka w liczbach',
		'<!-- wp:group {"className":"bdc-stats","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":null}} -->
<div class="wp-block-group bdc-stats"><!-- wp:paragraph -->
<p><strong>' . $branch_count . '</strong> ' . $branch_word . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"align":"center","className":"bdc-more"} -->
<p class="has-text-align-center bdc-more"><a href="/nasza-biblioteka/">Poznaj historię i misję biblioteki</a></p>
<!-- /wp:paragraph -->'
	);
}

// The example library's numbers. For a real library the pattern exists by now, so this
// leaves it alone.
bdc_ensure_pattern(
	'liczby',
	'Strona główna: biblioteka w liczbach',
	'<!-- wp:group {"className":"bdc-stats","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":null}} -->
<div class="wp-block-group bdc-stats"><!-- wp:paragraph -->
<p><strong>3</strong> placówki biblioteczne</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong><em>około</em> 25 000</strong> woluminów</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong><em>blisko</em> 1 500</strong> stałych czytelników</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong><em>około</em> 12 000</strong> odwiedzin rocznie</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"align":"center","className":"bdc-more"} -->
<p class="has-text-align-center bdc-more"><a href="/nasza-biblioteka/">Poznaj historię i misję biblioteki</a></p>
<!-- /wp:paragraph -->'
);

$hours_ref = bdc_ensure_pattern(
	'godziny-otwarcia',
	'Godziny otwarcia',
	'<!-- wp:table {"className":"bdc-hours"} -->
<figure class="wp-block-table bdc-hours"><table class="has-fixed-layout"><tbody>' . implode( '', array_map( fn( $row ) => '<tr><td>' . esc_html( $row[0] ) . '</td><td>' . esc_html( $row[1] ) . '</td></tr>', $hours ) ) . '</tbody></table></figure>
<!-- /wp:table -->'
);

$main   = $branches[0];
$filie  = "<!-- wp:columns {\"verticalAlignment\":\"center\",\"style\":{\"spacing\":{\"blockGap\":{\"left\":\"var:preset|spacing|60\"}}}} -->\n<div class=\"wp-block-columns are-vertically-aligned-center\">";
$filie .= "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div id=\"{$main['id']}\" class=\"wp-block-column is-vertically-aligned-center\">" . bdc_branch_heading( $main ) . bdc_branch_details( $main, $hours_ref ) . "</div>\n<!-- /wp:column -->\n";
$filie .= "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n<div class=\"wp-block-column is-vertically-aligned-center\">" . bdc_branches_map( $branches ) . "</div>\n<!-- /wp:column -->\n";
$filie .= "</div>\n<!-- /wp:columns -->\n";
$filie .= "<!-- wp:group {\"style\":{\"spacing\":{\"blockGap\":\"var:preset|spacing|50\",\"margin\":{\"top\":\"var:preset|spacing|60\"}}},\"layout\":{\"type\":\"grid\",\"columnCount\":2,\"minimumColumnWidth\":\"20rem\"}} -->\n<div class=\"wp-block-group\" style=\"margin-top:var(--wp--preset--spacing--60)\">";
foreach ( array_slice( $branches, 1 ) as $branch ) {
	$filie .= "<!-- wp:group {\"className\":\"bdc-branch\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|40\",\"bottom\":\"var:preset|spacing|50\",\"left\":\"var:preset|spacing|40\",\"right\":\"var:preset|spacing|40\"}}},\"layout\":{\"type\":\"flex\",\"orientation\":\"vertical\",\"justifyContent\":\"center\"}} -->\n"
		. "<div id=\"{$branch['id']}\" class=\"wp-block-group bdc-branch\" style=\"padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)\">"
		. bdc_branch_heading( $branch ) . bdc_branch_details( $branch, $hours_ref )
		. "</div>\n<!-- /wp:group -->\n";
}
$filie .= "</div>\n<!-- /wp:group -->";
$filie_ref = bdc_ensure_pattern( 'filie', 'Filie (adresy i mapa)', $filie );

bdc_ensure_pattern(
	'partnerzy',
	'Partnerzy',
	'<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Tu są logotypy partnerów biblioteki. Dodaj je jako galerię w panelu, w Stałe treści → Partnerzy.</p>
<!-- /wp:paragraph -->'
);

bdc_ensure_pattern(
	'stopka-kontakt',
	'Stopka: dane kontaktowe',
	'<!-- wp:paragraph {"fontSize":"small","style":{"typography":{"fontWeight":"600"}}} -->
<p class="has-small-font-size" style="font-weight:600">' . esc_html( $library ) . '<br>' . esc_html( $main['street'] ) . '<br>' . esc_html( $main['city'] ) . '<br>' . bdc_contact_links( $main ) . '</p>
<!-- /wp:paragraph -->'
);

$items = array(
	array( get_permalink( $a11y ), 'Deklaracja dostępności' ),
);

$list = '';
foreach ( $items as list( $url, $label ) ) {
	$list .= '<!-- wp:list-item --><li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . "</a></li><!-- /wp:list-item -->\n";
}
bdc_ensure_pattern( 'stopka-linki', 'Stopka: dokumenty i linki', "<!-- wp:list -->\n<ul class=\"wp-block-list\">$list</ul>\n<!-- /wp:list -->" );

// The contact page shows the same branch details as the home page. Page content
// lives in the database, so it can reference the pattern by ID directly.
$contact = get_page_by_path( 'kontakt' );
if ( ! $contact ) {
	bdc_ensure_page( 'kontakt', 'Kontakt', "<!-- wp:block {\"ref\":$filie_ref} /-->" );
} elseif ( ! has_block( 'core/block', $contact ) ) {
	bdc_log( 'Replacing /kontakt/ content with the branches pattern (old content kept as a revision)' );
	wp_save_post_revision( $contact->ID );
	wp_update_post(
		array(
			'ID'           => $contact->ID,
			'post_content' => "<!-- wp:block {\"ref\":$filie_ref} /-->",
		)
	);
}

// Links to every branch's catalog, linked from the main menu. Created once;
// after that it's an ordinary page staff can edit.
$catalogs = '';
foreach ( $branches as $branch ) {
	if ( empty( $branch['catalog'] ) ) {
		continue;
	}
	$catalogs .= '<!-- wp:list-item --><li><a href="' . esc_url( $branch['catalog'] ) . '">' . esc_html( $branch['name'] ) . "</a></li><!-- /wp:list-item -->\n";
}
bdc_ensure_page(
	'katalog-online',
	'Katalog online',
	'<!-- wp:paragraph -->
<p>Każda nasza placówka ma własny katalog w serwisie SzukamKsiążki.pl. Wybierz placówkę, aby sprawdzić, czy książka jest w jej zbiorach.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"fontSize":"large"} -->
<ul class="wp-block-list has-large-font-size">' . $catalogs . '</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Adresy i godziny otwarcia placówek są na stronie <a href="/kontakt/">Kontakt</a>.</p>
<!-- /wp:paragraph -->'
);

flush_rewrite_rules();
WP_CLI::success( 'Setup done.' );
