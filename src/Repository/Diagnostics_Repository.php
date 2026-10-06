<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Diagnostics_Repository
 *
 * Liefert die Kennzahlen für den Konfigurationscheck der Übersichtsseite.
 *
 * Bewusst ausschliesslich Aggregate — Anzahlen, Existenz von Tabellen, Spalten. Es
 * werden nie Zeileninhalte gelesen: die Tabellen des webuntisAnalyser enthalten
 * personenbezogene Daten, und für die Frage "ist alles eingerichtet?" braucht es sie
 * nicht.
 */
class Diagnostics_Repository {

	public function __construct( private wpdb $db ) {}

	/** Existiert die Tabelle? Fremde Tabellen sind nie garantiert. */
	public function table_exists( string $table_suffix ): bool {
		$table = $this->db->prefix . $table_suffix;

		return $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/** Existiert eine Spalte? Zeigt an, ob ein Schema-Update durchgelaufen ist. */
	public function column_exists( string $table_suffix, string $column ): bool {
		if ( ! $this->table_exists( $table_suffix ) ) {
			return false;
		}
		$table = $this->db->prefix . $table_suffix;

		return ! empty( $this->db->get_results(
			$this->db->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column )
		) );
	}

	/**
	 * Zählt Zeilen, optional mit WHERE-Bedingung.
	 *
	 * @param string $where Feste, im Code hinterlegte Bedingung ohne Benutzereingaben.
	 */
	public function count( string $table_suffix, string $where = '' ): int {
		if ( ! $this->table_exists( $table_suffix ) ) {
			return -1; // Tabelle fehlt — unterscheidbar von "null Zeilen"
		}
		$table  = $this->db->prefix . $table_suffix;
		$clause = '' !== $where ? " WHERE $where" : '';

		return (int) $this->db->get_var( "SELECT COUNT(*) FROM `$table`$clause" );
	}

	/**
	 * Bildungsgänge, für die eine Stundentafel hinterlegt ist. Ohne sie bleibt die
	 * Fächer-Vorbelegung im Abgangsformular leer.
	 */
	public function tracks_with_stundentafel(): int {
		if ( ! $this->table_exists( 'wa_track_subjects' ) ) {
			return -1;
		}
		$table = $this->db->prefix . 'wa_track_subjects';

		return (int) $this->db->get_var( "SELECT COUNT(DISTINCT track_key) FROM `$table`" );
	}

	/**
	 * Klassen, deren zugeordneter Bildungsgang gar keine Stundentafel hat — der Fall,
	 * der im Formular am schwersten zu erkennen ist, weil die Zuordnung ja gesetzt ist.
	 */
	public function classes_with_track_but_no_stundentafel(): int {
		if ( ! $this->column_exists( 'wa_classes', 'track_key' ) || ! $this->table_exists( 'wa_track_subjects' ) ) {
			return -1;
		}
		$classes = $this->db->prefix . 'wa_classes';
		$ts      = $this->db->prefix . 'wa_track_subjects';

		return (int) $this->db->get_var(
			"SELECT COUNT(*) FROM `$classes` c
			 WHERE c.is_active = 1 AND c.teacher_1 <> '' AND c.track_key <> ''
			   AND NOT EXISTS ( SELECT 1 FROM `$ts` ts WHERE ts.track_key = c.track_key )"
		);
	}
}
