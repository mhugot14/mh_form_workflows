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
		$query = $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		);

		$row = $this->db->get_row( $query, ARRAY_A );

		if ( null === $row ) {
			return null;
		}
		
		// JSON decode form_data zurück zu Array
		if ( isset( $row['form_data'] ) ) {
			$row['form_data'] = json_decode( $row['form_data'], true );
		}

		return $row;
	}
}