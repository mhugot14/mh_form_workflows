<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Background_Response
 *
 * Schickt dem Browser die Weiterleitung sofort und lässt PHP danach weiterarbeiten.
 *
 * Gebraucht für den Mailversand: ein langsamer oder hängender Mailserver soll weder
 * die Seite blockieren (Tab "dreht sich") noch den Aufruf vor dem Versand abbrechen.
 * Der Aufrufer erledigt nach redirect_and_continue() die eigentliche Arbeit und
 * beendet dann selbst mit exit.
 */
final class Background_Response {

	public static function redirect_and_continue( string $url ): void {
		// Weiterarbeiten, auch wenn der Browser die Verbindung nach der Weiterleitung schließt.
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- auf manchen Hostern gesperrt
		}

		wp_redirect( $url );

		// PHP-FPM bzw. LiteSpeed: Antwort sauber abschließen, Skript läuft weiter.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
			return;
		}

		// mod_php (z. B. XAMPP): leere Antwort mit fester Länge - der Browser folgt der
		// Weiterleitung, ohne auf das Skriptende zu warten.
		header( 'Connection: close' );
		header( 'Content-Length: 0' );
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();
	}
}
