<?php
/**
 * Plugin Name: Biblioteka SMTP
 * Description: Sends WordPress mail through the SMTP server in the SMTP_* environment variables. Does nothing when SMTP_HOST is unset.
 *
 * Port 587 with STARTTLS: Hetzner blocks outbound port 25.
 */

defined( 'ABSPATH' ) || exit;

if ( ! getenv( 'SMTP_HOST' ) ) {
	return;
}

add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$mailer->isSMTP();
		$mailer->Host       = getenv( 'SMTP_HOST' );
		$mailer->Port       = (int) ( getenv( 'SMTP_PORT' ) ?: 587 );
		$mailer->SMTPSecure = 'tls';
		$mailer->SMTPAuth   = (bool) getenv( 'SMTP_USER' );
		$mailer->Username   = (string) getenv( 'SMTP_USER' );
		$mailer->Password   = (string) getenv( 'SMTP_PASSWORD' );
		if ( getenv( 'SMTP_FROM' ) ) {
			$mailer->setFrom( getenv( 'SMTP_FROM' ), get_bloginfo( 'name' ), false );
		}
	}
);
