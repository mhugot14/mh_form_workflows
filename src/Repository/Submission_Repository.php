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
	/**
	 * Holt alle Einsendungen für den Admin, inklusive Benutzername.
	 */
	public function get_all_submissions_with_users(): array {
		global $wpdb;
		$query = "
			SELECT s.*, u.display_name as user_name
			FROM {$this->table_name} s
			LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
			ORDER BY s.created_at DESC
		";
		return $this->db->get_results( $query, ARRAY_A );
	}

    /**
     * Löscht einen Eintrag ohne User-Einschränkung (Admin-Power).
     */
    public function delete_as_admin( int $entry_id ): bool {
        return (bool) $this->db->delete( $this->table_name, [ 'id' => $entry_id ], [ '%d' ] );
    }
	/**
	 * Holt gefilterte Einsendungen für den Admin.
	 * 
	 * @param array $filters ['start_date', 'end_date', 'user_id', 'form_type']
	 */
	public function get_filtered_submissions( array $filters = [] ): array {
		global $wpdb;
		
		$where  = [ '1=1' ];
		$params = [];

		// Filter: Datumsbereich
		if ( ! empty( $filters['start_date'] ) ) {
			$where[]  = "s.created_at >= %s";
			$params[] = $filters['start_date'] . ' 00:00:00';
		}
		if ( ! empty( $filters['end_date'] ) ) {
			$where[]  = "s.created_at <= %s";
			$params[] = $filters['end_date'] . ' 23:59:59';
		}

		// Filter: Ersteller
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = "s.user_id = %d";
			$params[] = (int) $filters['user_id'];
		}

		// Filter: Formulartyp
		if ( ! empty( $filters['form_type'] ) ) {
			$where[]  = "s.form_type = %s";
			$params[] = $filters['form_type'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "
			SELECT s.*, u.display_name as user_name
			FROM {$this->table_name} s
			LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
			WHERE $where_clause
			ORDER BY s.created_at DESC
		";

		if ( empty( $params ) ) {
			return $this->db->get_results( $query, ARRAY_A );
		}

		return $this->db->get_results( $this->db->prepare( $query, ...$params ), ARRAY_A );
	}

	/**
	 * Hilfsmethode: Holt alle User-IDs, die jemals etwas eingesendet haben (für den Filter-Dropdown).
	 */
	public function get_distinct_submitters(): array {
		global $wpdb;
		return $this->db->get_results( "
			SELECT DISTINCT s.user_id, u.display_name 
			FROM {$this->table_name} s
			JOIN {$wpdb->users} u ON s.user_id = u.ID
			ORDER BY u.display_name ASC
		", ARRAY_A );
	}
	
	/**
	 * Löscht mehrere Einträge gleichzeitig.
	 * 
	 * @param array $ids Array von Integer-IDs
	 * @return int Anzahl der gelöschten Zeilen
	 */
	public function delete_multiple( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;
		// Erzeugt Platzhalter: %d, %d, %d...
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		
		$query = $wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
			...$ids
		);

		return (int) $wpdb->query( $query );
	}
	
	/**
	 * Aktualisiert einen bestehenden Datensatz.
	 */
	public function update( int $id, array $data, int $user_id ): bool {
		// Daten für DB vorbereiten (JSON encoding)
		if ( isset( $data['form_data'] ) && is_array( $data['form_data'] ) ) {
			$data['form_data'] = wp_json_encode( $data['form_data'] );
		}

		$data['updated_at'] = current_time( 'mysql' );

		$updated = $this->db->update(
			$this->table_name,
			$data,
			[ 'id' => $id, 'user_id' => $user_id ], // Nur wenn ID und User passen
			null,
			[ '%d', '%d' ]
		);

		return $updated !== false;
	}
}