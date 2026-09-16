<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Track_Subject_Repository
 *
 * Liest die Stundentafel (Bildungsgang -> Faecher) aus den Tabellen des
 * webuntisAnalyser-Plugins. Die Stundentafel ist die einzige Quelle, die die
 * Regelfaecher eines Bildungsgangs ganzjaehrig kennt: schuelerbezogen stehen sie
 * in Schild erst nach dem WebUntis-Import kurz vor der Notensammlung.
 */
class Track_Subject_Repository {

	private string $table_name;
	private string $subjects_table;

	public function __construct( private wpdb $db ) {
		$this->table_name     = $this->db->prefix . 'wa_track_subjects';
		$this->subjects_table = $this->db->prefix . 'wa_subjects';
	}

	/**
	 * Holt die Faecher eines Bildungsgangs in der Reihenfolge der Stundentafel.
	 *
	 * @param string $track_key Bildungsgang-Schluessel aus Schild (z.B. "WG").
	 * @return array Liste mit short_name und display_name.
	 */
	public function get_subjects_for_track( string $track_key ): array {
		if ( '' === $track_key ) {
			return [];
		}
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		// LEFT JOIN: Faecher, die (noch) nicht in der Schild-Faecherliste stehen,
		// sollen trotzdem erscheinen — sonst fehlen sie im Formular kommentarlos.
		$query = $this->db->prepare(
			"SELECT ts.subject_short AS short_name, s.display_name
             FROM {$this->table_name} ts
             LEFT JOIN {$this->subjects_table} s
                    ON ts.subject_short = s.short_name AND s.is_active = 1
             WHERE ts.track_key = %s
             ORDER BY ts.sort_order ASC, ts.subject_short ASC",
			$track_key
		);

		return $this->db->get_results( $query, ARRAY_A );
	}
}
