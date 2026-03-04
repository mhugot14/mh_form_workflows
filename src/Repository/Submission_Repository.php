<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

class Submission_Repository implements Submission_Repository_Interface {

	private wpdb $db;
	private string $table_name;

	public function __construct( wpdb $db ) {
		$this->db         = $db;
		$this->table_name = $this->db->prefix . 'mh_form_submissions';
	}

	/**
	 * Speichert einen neuen Datensatz.
	 *
	 * @param array $data Assoziatives Array mit Spaltennamen als Keys.
	 * @return int Die ID des neuen Eintrags oder 0 bei Fehler.
	 */
	public function create( array $data ): int {
		// Konvertiere das Daten-Array (form_data) in JSON
		if ( isset( $data['form_data'] ) && is_array( $data['form_data'] ) ) {
			$data['form_data'] = wp_json_encode( $data['form_data'] );
		}

		// Defaults setzen
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$inserted = $this->db->insert(
			$this->table_name,
			$data
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $this->db->insert_id;
	}

	public function get_by_id( int $id ): ?array {
    $row = $this->db->get_row( $this->db->prepare( 
        "SELECT * FROM {$this->table_name} WHERE id = %d", 
        $id 
    ), ARRAY_A );

    if ( $row && !empty($row['form_data']) ) {
        // Falls es noch ein JSON-String ist, decoden
        if ( is_string($row['form_data']) ) {
            $row['form_data'] = json_decode($row['form_data'], true);
        }
    }
    return $row;
}
	/**
	 * Holt alle Einsendungen eines bestimmten Users, sortiert nach Datum (neu oben).
	 */
	public function get_submissions_by_user( int $user_id ): array {
		return $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY created_at DESC",
			$user_id
		), ARRAY_A );
	}

	/**
	 * Löscht einen Eintrag (nur wenn er dem User gehört).
	 */
	public function delete_submission( int $entry_id, int $user_id ): bool {
		$deleted = $this->db->delete(
			$this->table_name,
			[ 'id' => $entry_id, 'user_id' => $user_id ], // Where
			[ '%d', '%d' ] // Formate
		);
		return $deleted !== false && $deleted > 0;
	}
}