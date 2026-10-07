<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Dashboard_Link
 *
 * Link zurück zur Dashboard-Seite (Einstellung "page_id_mh_dashboard").
 *
 * Wird zentral über die Ausgabe aller Plugin-Shortcodes gesetzt (siehe
 * Plugin_Bootstrap), damit nicht jedes Template einen eigenen Rückweg pflegen muss
 * und neue Seiten ihn automatisch bekommen.
 */
final class Dashboard_Link {

	/** Pro Seitenaufruf nur ein Link, auch wenn mehrere Shortcodes auf der Seite stehen. */
	private static bool $rendered = false;

	/**
	 * URL der Dashboard-Seite. Leer, wenn keine Seite zugeordnet ist.
	 */
	public static function url(): string {
		$page_id = self::page_id();

		return $page_id > 0 ? (string) ( get_permalink( $page_id ) ?: '' ) : '';
	}

	/**
	 * HTML für den Rückweg - oder leer, wenn er nicht passt: keine Dashboard-Seite
	 * hinterlegt, nicht angemeldet (das Dashboard zeigt dann nur einen Login-Hinweis),
	 * man ist bereits auf dem Dashboard oder der Link steht schon auf der Seite.
	 */
	public static function back_link_html(): string {
		if ( self::$rendered || ! is_user_logged_in() ) {
			return '';
		}

		$page_id = self::page_id();
		if ( $page_id <= 0 || get_queried_object_id() === $page_id ) {
			return '';
		}

		$url = self::url();
		if ( '' === $url ) {
			return '';
		}

		self::$rendered = true;

		// Beschriftet wird mit dem Titel der Dashboard-Seite (z. B. "Formular-Center"),
		// damit Link und Menüpunkt gleich heißen.
		$title = trim( wp_strip_all_tags( get_the_title( $page_id ) ) );
		if ( '' === $title ) {
			$title = 'Dashboard';
		}

		return '<p class="mh-back-to-dashboard" style="margin:0 0 15px 0; font-size:0.95em;">'
			. '<a href="' . esc_url( $url ) . '" style="text-decoration:none;">&larr; Zurück zum ' . esc_html( $title ) . '</a>'
			. '</p>';
	}

	/** Ergebnis der Suche, damit sie pro Seitenaufruf nur einmal läuft. */
	private static ?int $page_id = null;

	/**
	 * Zugeordnete Dashboard-Seite. Ist keine zugeordnet (die Einstellung ist optional),
	 * wird die veröffentlichte Seite mit [mh_dashboard] gesucht - wie im
	 * Konfigurationscheck: Volltextsuche als Vorauswahl, entschieden per has_shortcode().
	 */
	private static function page_id(): int {
		if ( null !== self::$page_id ) {
			return self::$page_id;
		}

		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_dashboard'] ?? 0 );

		if ( $page_id <= 0 ) {
			$candidates = get_posts( [
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				's'              => '[mh_dashboard]',
			] );
			foreach ( $candidates as $page ) {
				if ( has_shortcode( (string) $page->post_content, 'mh_dashboard' ) ) {
					$page_id = (int) $page->ID;
					break;
				}
			}
		}

		return self::$page_id = $page_id;
	}
}
