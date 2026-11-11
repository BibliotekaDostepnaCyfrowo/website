<?php
/**
 * Theme updates from this repository's GitHub releases.
 *
 * The theme isn't on WordPress.org. Libraries' sites run at their own hosting
 * providers, so a fix made here would otherwise reach them only by uploading the
 * theme by hand. With `Update URI` in style.css, WordPress asks the
 * `update_themes_github.com` filter about this theme during its normal update
 * check (twice a day, and when Kokpit → Aktualizacje is opened). The filter reads
 * theme-update.json from the latest release; a newer version there shows up as an
 * ordinary theme update in wp-admin, and is installed automatically if the site's
 * administrator turned on auto-updates for the theme (Wygląd → Motywy).
 *
 * The release is built by .github/workflows/theme-release.yml; the steps are in
 * wordpress/README.md, "Theme releases". The manifest, for this filter and for
 * anyone comparing a site's theme version with the latest release:
 *
 *     { "theme": "biblioteka-dostepna-cyfrowo", "version": "2.2.0",
 *       "package": "https://github.com/…/releases/download/theme-v2.2.0/biblioteka-dostepna-cyfrowo-2.2.0.zip",
 *       "sha256": "…", "url": "https://github.com/…/releases/tag/theme-v2.2.0",
 *       "requires": "6.6", "requires_php": "8.1", "released": "2026-11-11T12:00:00Z" }
 *
 * The downloaded zip must match `sha256`, or the update stops before anything is
 * replaced. Where code can't be changed from the admin (DISALLOW_FILE_MODS, as on
 * our Docker image, which brings the theme with it) none of this is hooked.
 *
 * BDC_THEME_UPDATE_MANIFEST in wp-config.php points the check at another manifest
 * URL (to test a release, or to move the channel off GitHub).
 */

defined( 'ABSPATH' ) || exit;

/** Must equal `Update URI` in style.css; WordPress picks the filter by its hostname. */
const BDC_UPDATE_URI = 'https://github.com/BibliotekaDostepnaCyfrowo/website';

const BDC_UPDATE_MANIFEST = 'https://github.com/BibliotekaDostepnaCyfrowo/website/releases/latest/download/theme-update.json';

/** The theme's directory name; a release zip unpacks into a folder of this name. */
const BDC_UPDATE_THEME = 'biblioteka-dostepna-cyfrowo';

/** The manifest URL this site checks. */
function bdc_update_manifest_url() {
	return defined( 'BDC_THEME_UPDATE_MANIFEST' ) ? (string) BDC_THEME_UPDATE_MANIFEST : BDC_UPDATE_MANIFEST;
}

/**
 * Turns a decoded theme-update.json into the update offer WordPress expects, or
 * null if it isn't a usable manifest for this theme.
 *
 * @param mixed $manifest Decoded JSON.
 * @return array|null
 */
function bdc_update_offer( $manifest ) {
	if ( ! is_array( $manifest ) || BDC_UPDATE_THEME !== ( $manifest['theme'] ?? null ) ) {
		return null;
	}
	$version = $manifest['version'] ?? '';
	$package = $manifest['package'] ?? '';
	$sha256  = $manifest['sha256'] ?? '';
	if ( ! is_string( $version ) || ! preg_match( '/^\d+(\.\d+){0,3}$/', $version )
		|| ! is_string( $package ) || ! wp_http_validate_url( $package )
		|| ! is_string( $sha256 ) || ! preg_match( '/^[0-9a-f]{64}$/', $sha256 ) ) {
		return null;
	}
	// Off GitHub only when the site was pointed at another manifest on purpose.
	if ( ! defined( 'BDC_THEME_UPDATE_MANIFEST' ) && ! str_starts_with( $package, BDC_UPDATE_URI . '/releases/download/' ) ) {
		return null;
	}
	$text = fn( $key ) => is_string( $manifest[ $key ] ?? null ) ? $manifest[ $key ] : '';
	return array(
		'theme'        => BDC_UPDATE_THEME,
		'version'      => $version,
		'new_version'  => $version,
		'package'      => $package,
		'url'          => $text( 'url' ) ? $text( 'url' ) : BDC_UPDATE_URI . '/releases',
		'requires'     => $text( 'requires' ),
		'requires_php' => $text( 'requires_php' ),
		// Not a field WordPress knows: the upgrader keeps it, and the download is checked against it.
		'sha256'       => $sha256,
	);
}

if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
	return;
}

add_filter(
	'update_themes_' . wp_parse_url( BDC_UPDATE_URI, PHP_URL_HOST ),
	function ( $update, $theme_data, $stylesheet ) {
		if ( BDC_UPDATE_URI !== ( $theme_data['UpdateURI'] ?? '' ) || BDC_UPDATE_THEME !== $stylesheet ) {
			return $update;
		}
		$response = wp_remote_get(
			bdc_update_manifest_url(),
			array(
				'timeout'    => wp_doing_cron() ? 30 : 5,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . BDC_UPDATE_THEME,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $update;
		}
		$offer = bdc_update_offer( json_decode( wp_remote_retrieve_body( $response ), true ) );
		return $offer ?? $update;
	},
	10,
	3
);

// WordPress.org is asked about every installed theme. Leave this one out, so a
// theme published there under the same folder name could never be offered instead.
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( ! str_contains( $url, '://api.wordpress.org/themes/update-check/' ) || ! is_string( $args['body']['themes'] ?? null ) ) {
			return $args;
		}
		$request = json_decode( $args['body']['themes'], true );
		if ( ! is_array( $request['themes'] ?? null ) ) {
			return $args;
		}
		$before            = count( $request['themes'] );
		$request['themes'] = array_filter(
			$request['themes'],
			fn( $theme ) => BDC_UPDATE_URI !== ( $theme['UpdateURI'] ?? '' )
		);
		if ( count( $request['themes'] ) !== $before ) {
			$args['body']['themes'] = wp_json_encode( $request );
		}
		return $args;
	},
	10,
	2
);

// Download the release zip ourselves and check it against the manifest's sha256.
add_filter(
	'upgrader_pre_download',
	function ( $reply, $package, $upgrader, $hook_extra ) {
		if ( false !== $reply || BDC_UPDATE_THEME !== ( $hook_extra['theme'] ?? null ) ) {
			return $reply;
		}
		$offer = get_site_transient( 'update_themes' )->response[ BDC_UPDATE_THEME ] ?? null;
		if ( ! is_array( $offer ) || ( $offer['package'] ?? null ) !== $package || empty( $offer['sha256'] ) ) {
			return $reply;
		}
		$upgrader->skin->feedback( 'downloading_package', $package );
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return new WP_Error( 'download_failed', $upgrader->strings['download_failed'], $file->get_error_message() );
		}
		if ( ! hash_equals( $offer['sha256'], (string) hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'bdc_checksum', 'Pobrany plik motywu nie zgadza się z sumą kontrolną wydania (sha256). Aktualizację przerwano.' );
		}
		return $file;
	},
	10,
	4
);
