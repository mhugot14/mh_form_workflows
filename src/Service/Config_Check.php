<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

use Mh\FormWorkflows\Repository\Diagnostics_Repository;

/**
 * Class Config_Check
 *
 * Prüft, ob das Plugin vollständig eingerichtet ist, und beschreibt jeden Befund so,
 * dass daraus eine Handlung folgt.
 *
 * Drei Stufen: 'ok' (erledigt), 'warn' (funktioniert, aber unvollständig — etwa ein
 * optionales Formular ohne Seite) und 'error' (etwas Zentrales fehlt, der Ablauf
 * bricht). Die Unterscheidung ist wichtig, weil sonst alles gleich dringend aussieht
 * und am Ende niemand mehr hinschaut.
 */
class Config_Check {

	/** Shortcode je Einstellungsschlüssel — zugleich die Liste der zu prüfenden Seiten. */
	private const PAGES = [
		'page_id_abmeldung_student_v1' => [ 'Schüler*innen-Abmeldung', 'mh_form_workflow', 'error' ],
		'page_id_mh_my_submissions'    => [ 'Meine Abmeldungen', 'mh_my_submissions', 'warn' ],
		'page_id_mh_absentismus_fall'  => [ 'Absentismus-Fall', 'mh_absentismus_fall', 'error' ],
		'page_id_mh_absentismus_liste' => [ 'Absentismus-Übersicht', 'mh_absentismus_liste', 'warn' ],
		'page_id_mh_noten_eingabe'     => [ 'Noteneingabe (Fachlehrkraft)', 'mh_noten_eingabe', 'error' ],
		'page_id_mh_noten_fall'        => [ 'Noten-Fall (Klassenleitung)', 'mh_noten_fall', 'warn' ],
		'page_id_mh_noten_liste'       => [ 'Meine Noteneingaben', 'mh_noten_liste', 'warn' ],
		'page_id_service_leave_v1'     => [ 'Dienstbefreiung', 'mh_form_workflow', 'warn' ],
	];

	public function __construct( private Diagnostics_Repository $diag ) {}

	/**
	 * @return array{groups: array, counts: array{ok:int, warn:int, error:int}}
	 */
	public function run(): array {
		$groups = [
			'Seiten & Shortcodes' => $this->check_pages(),
			'Datenbasis'          => $this->check_data(),
			'Noteneinsammlung'    => $this->check_noten(),
			'System'              => $this->check_system(),
		];

		$counts = [ 'ok' => 0, 'warn' => 0, 'error' => 0 ];
		foreach ( $groups as $items ) {
			foreach ( $items as $item ) {
				$counts[ $item['level'] ]++;
			}
		}

		return [ 'groups' => $groups, 'counts' => $counts ];
	}

	private function item( string $level, string $label, string $text, string $hint = '' ): array {
		return [ 'level' => $level, 'label' => $label, 'text' => $text, 'hint' => $hint ];
	}

	/**
	 * Für jede Zielseite: ist sie zugeordnet, existiert sie noch, und steht der
	 * Shortcode auch wirklich drin? Der dritte Fall ist der tückische — die Zuordnung
	 * sieht richtig aus, die Seite bleibt aber leer.
	 */
	private function check_pages(): array {
		$options = get_option( 'mh_fw_settings', [] );
		$items   = [];

		foreach ( self::PAGES as $key => $spec ) {
			[ $label, $shortcode, $missing_level ] = $spec;

			$page_id = (int) ( $options[ $key ] ?? 0 );

			if ( $page_id <= 0 ) {
				$items[] = $this->item(
					$missing_level,
					$label,
					'Keine Seite zugeordnet.',
					'Einstellungen: eine Seite mit dem Shortcode [' . $shortcode . '] auswählen.'
				);
				continue;
			}

			$post = get_post( $page_id );
			if ( null === $post || 'trash' === $post->post_status ) {
				$items[] = $this->item(
					'error',
					$label,
					'Die zugeordnete Seite existiert nicht mehr (ID ' . $page_id . ').',
					'Bitte in den Einstellungen neu zuordnen.'
				);
				continue;
			}

			if ( ! has_shortcode( (string) $post->post_content, $shortcode ) ) {
				$items[] = $this->item(
					'warn',
					$label,
					'Seite "' . $post->post_title . '" zugeordnet, enthält aber nicht [' . $shortcode . '].',
					'Shortcode in die Seite einfügen, sonst bleibt sie leer.'
				);
				continue;
			}

			$ist_public = 'publish' === $post->post_status;
			$items[]    = $this->item(
				$ist_public ? 'ok' : 'warn',
				$label,
				'Seite "' . $post->post_title . '"' . ( $ist_public ? '.' : ' (Status: ' . $post->post_status . ').' ),
				$ist_public ? '' : 'Nur veröffentlichte Seiten sind für Kolleg*innen erreichbar.'
			);
		}

		$items[] = $this->dashboard_page_item();

		return $items;
	}

	/**
	 * Das Dashboard hat bewusst keine Einstellung — es wird nicht vom Plugin verlinkt,
	 * sondern von Hand ins Menü gehängt. Deshalb Suche statt Nachschlagen.
	 */
	private function dashboard_page_item(): array {
		// Die Zuordnung ist optional: das Dashboard funktioniert auch ohne sie, weil
		// niemand es aus dem Plugin heraus verlinkt. Gebraucht wird sie erst, damit die
		// Mails der Noteneinsammlung darauf verweisen können.
		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_dashboard'] ?? 0 );

		if ( $page_id > 0 ) {
			$post = get_post( $page_id );

			if ( null === $post || 'trash' === $post->post_status ) {
				return $this->item( 'error', 'Dashboard',
					'Die zugeordnete Seite existiert nicht mehr (ID ' . $page_id . ').',
					'Bitte in den Einstellungen neu zuordnen.' );
			}
			if ( ! has_shortcode( (string) $post->post_content, 'mh_dashboard' ) ) {
				return $this->item( 'warn', 'Dashboard',
					'Seite "' . $post->post_title . '" zugeordnet, enthält aber nicht [mh_dashboard].',
					'Shortcode in die Seite einfügen, sonst bleibt sie leer.' );
			}
			if ( 'publish' !== $post->post_status ) {
				return $this->item( 'warn', 'Dashboard',
					'Seite "' . $post->post_title . '" (Status: ' . $post->post_status . ').',
					'Nur veröffentlichte Seiten sind für Kolleg*innen erreichbar.' );
			}

			return $this->item( 'ok', 'Dashboard', 'Seite "' . $post->post_title . '", verknüpft.' );
		}

		// Ohne Zuordnung: nachsehen, ob es die Seite trotzdem gibt. Die Volltextsuche ist
		// dabei nur die Vorauswahl, sie trifft auch Seiten, die den Shortcode bloss
		// erwähnen. Entschieden wird über has_shortcode(), wie bei den anderen Seiten.
		$candidates = get_posts( [
			'post_type'      => 'page',
			'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
			'posts_per_page' => 20,
			's'              => '[mh_dashboard]',
		] );

		$published = null;
		$other     = null;
		foreach ( $candidates as $page ) {
			if ( ! has_shortcode( (string) $page->post_content, 'mh_dashboard' ) ) {
				continue;
			}
			if ( 'publish' === $page->post_status ) {
				$published = $page;
				break;
			}
			$other ??= $page;
		}

		if ( null !== $published ) {
			return $this->item( 'warn', 'Dashboard',
				'Seite "' . $published->post_title . '" gefunden, aber in den Einstellungen nicht zugeordnet.',
				'Ohne Zuordnung verweisen die Mails der Noteneinsammlung nicht auf das Dashboard.' );
		}

		if ( null !== $other ) {
			return $this->item(
				'warn',
				'Dashboard',
				'Seite "' . $other->post_title . '" enthält den Shortcode, ist aber nicht veröffentlicht (Status: ' . $other->post_status . ').',
				'Nur veröffentlichte Seiten sind für Kolleg*innen erreichbar.'
			);
		}

		return $this->item(
			'warn',
			'Dashboard',
			'Keine Seite mit [mh_dashboard] gefunden.',
			'Eine Seite mit diesem Shortcode anlegen, ins Menü hängen und in den Einstellungen zuordnen.'
		);
	}

	/** Stammdaten aus dem webuntisAnalyser. */
	private function check_data(): array {
		$items = [];

		if ( ! $this->diag->table_exists( 'wa_classes' ) ) {
			return [ $this->item(
				'error',
				'WebUntis Analyser',
				'Die Tabellen des Plugins "WebUntis Analyser" fehlen.',
				'Plugin installieren und aktivieren: ohne seine Stammdaten bleiben alle Auswahlfelder leer.'
			) ];
		}

		$klassen = $this->diag->count( 'wa_classes', "is_active = 1 AND teacher_1 <> ''" );
		$items[] = $klassen > 0
			? $this->item( 'ok', 'Klassen', $klassen . ' aktive Klassen mit Klassenleitung.' )
			: $this->item( 'error', 'Klassen', 'Keine aktive Klasse mit Klassenleitung.',
				'Im WebUntis Analyser unter "Klassen" synchronisieren.' );

		$schueler = $this->diag->count( 'wa_students', 'is_active = 1' );
		$items[]  = $schueler > 0
			? $this->item( 'ok', 'Schüler*innen', $schueler . ' Datensätze importiert.' )
			: $this->item( 'error', 'Schüler*innen', 'Keine Schülerdaten importiert.',
				'Im WebUntis Analyser unter "Schüler" die Schild-CSV einlesen.' );

		$faecher = $this->diag->count( 'wa_subjects', 'is_active = 1' );
		$items[] = $faecher > 0
			? $this->item( 'ok', 'Fächerliste', $faecher . ' Fächer.' )
			: $this->item( 'warn', 'Fächerliste', 'Keine Fächer importiert.',
				'Ohne Fächerliste erscheinen in der Stundentafel nur Kürzel ohne Bezeichnung.' );

		$items = array_merge( $items, $this->check_bildungsgaenge() );

		$kurse   = $this->diag->count( 'wa_student_courses' );
		$items[] = $kurse > 0
			? $this->item( 'ok', 'Kursbelegungen', $kurse . ' Belegungen.' )
			: $this->item( 'warn', 'Kursbelegungen', 'Keine Kursbelegungen importiert.',
				'Optional. Ohne sie fehlen im Abgangsformular die Kurse samt Kurslehrkraft.' );

		return $items;
	}

	/** Bildungsgänge, Klassenzuordnung und Stundentafeln: die Kette für die Fächer-Vorbelegung. */
	private function check_bildungsgaenge(): array {
		$items  = [];
		$tracks = $this->diag->count( 'wa_education_tracks', 'is_active = 1' );

		if ( $tracks <= 0 ) {
			return [ $this->item( 'error', 'Bildungsgänge', 'Keine Bildungsgänge angelegt.',
				'Im WebUntis Analyser unter "Bildungsgänge" den Schild-Export einspielen.' ) ];
		}

		$mit_tafel = $this->diag->tracks_with_stundentafel();
		$items[]   = $mit_tafel >= $tracks
			? $this->item( 'ok', 'Bildungsgänge', $tracks . ' Bildungsgänge, alle mit Stundentafel.' )
			: $this->item( $mit_tafel > 0 ? 'warn' : 'error', 'Bildungsgänge',
				$tracks . ' Bildungsgänge, davon ' . max( 0, $mit_tafel ) . ' mit Stundentafel.',
				'Für Bildungsgänge ohne Stundentafel wird im Abgangsformular kein Fach vorbelegt.' );

		if ( ! $this->diag->column_exists( 'wa_classes', 'track_key' ) ) {
			$items[] = $this->item( 'error', 'Klassen zu Bildungsgang',
				'Die Spalte track_key fehlt in wa_classes.',
				'Eine Adminseite des WebUntis Analyser aufrufen: das Schema-Update läuft dann automatisch.' );

			return $items;
		}

		$ohne    = $this->diag->count( 'wa_classes', "is_active = 1 AND teacher_1 <> '' AND track_key = ''" );
		$items[] = 0 === $ohne
			? $this->item( 'ok', 'Klassen zu Bildungsgang', 'Alle Klassen sind einem Bildungsgang zugeordnet.' )
			: $this->item( 'warn', 'Klassen zu Bildungsgang', $ohne . ' Klassen ohne Bildungsgang.',
				'WebUntis Analyser, Klassen, "Bildungsgänge aus Schülerdaten ableiten".' );

		$leer = $this->diag->classes_with_track_but_no_stundentafel();
		if ( $leer > 0 ) {
			$items[] = $this->item( 'warn', 'Stundentafel fehlt',
				$leer . ' Klassen zeigen auf einen Bildungsgang ohne Stundentafel.',
				'Die Zuordnung sieht vollständig aus, die Fächer-Vorbelegung bleibt aber leer.' );
		}

		return $items;
	}

	/** Die digitale Noteneinsammlung hängt an Cron und an erreichbaren Adressen. */
	private function check_noten(): array {
		$items = [];

		$next    = wp_next_scheduled( Reminder_Service::CRON_HOOK );
		$items[] = $next
			? $this->item( 'ok', 'Erinnerungs-Cron', 'Nächster Lauf: ' . date_i18n( 'd.m.Y H:i', (int) $next ) . '.' )
			: $this->item( 'error', 'Erinnerungs-Cron', 'Der tägliche Lauf ist nicht eingeplant.',
				'Plugin deaktivieren und wieder aktivieren: der Hook wird beim Aktivieren gesetzt.' );

		$items[] = ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON )
			? $this->item( 'warn', 'WP-Cron', 'DISABLE_WP_CRON ist gesetzt.',
				'Dann muss auf dem Server ein echter Cron-Job wp-cron.php regelmässig aufrufen.' )
			: $this->item( 'warn', 'WP-Cron', 'WordPress-Cron feuert nur bei Seitenaufrufen.',
				'Für verlässliche Erinnerungsfristen sollte ein Server-Cron wp-cron.php stündlich aufrufen.' );

		$konten  = $this->diag->count( 'wa_teacher_accounts', "notify_email <> '' OR wp_user_id > 0" );
		$items[] = $konten > 0
			? $this->item( 'ok', 'Lehrer-Zuordnung', $konten . ' Lehrkräfte mit erreichbarer Adresse.' )
			: $this->item( 'warn', 'Lehrer-Zuordnung', 'Keine Lehrkraft hat eine hinterlegte Adresse.',
				'Ohne auflösbare Adresse lässt sich die Noteneinsammlung nicht starten.' );

		return $items;
	}

	/** PDF-Erzeugung und Altlasten. */
	private function check_system(): array {
		$items = [];

		$items[] = class_exists( '\Dompdf\Dompdf' )
			? $this->item( 'ok', 'PDF-Bibliothek', 'Dompdf ist geladen.' )
			: $this->item( 'error', 'PDF-Bibliothek', 'Dompdf fehlt.',
				'Der vendor-Ordner ist unvollständig: "composer install" ausführen oder vendor/ mit hochladen.' );

		$altlast = $this->diag->count( 'mh_form_submissions', "form_type = 'service_leave_v1'" );
		if ( $altlast > 0 ) {
			$items[] = $this->item( 'warn', 'Altlasten',
				$altlast . ' gespeicherte Dienstbefreiung(en) in der Datenbank.',
				'Dienstbefreiungen werden nicht mehr gespeichert. Entfernen unter Einstellungen, Wartung.' );
		}

		return $items;
	}
}
