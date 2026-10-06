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
	 * Formulartypen, die als Antrag gespeichert werden und später wieder abrufbar sind.
	 *
	 * Nur die Abmeldung: Die Dienstbefreiung wird bewusst nicht gespeichert, sondern
	 * direkt als PDF ausgeliefert (siehe Form_Controller::handle_submission()). In
	 * derselben Tabelle liegen ausserdem Absentismus- und Noten-Fälle — das sind keine
	 * Anträge, sondern laufende Vorgänge mit eigener Oberfläche, und ihr PDF-Download
	 * ginge hier ins Leere.
	 */
	public const ANTRAG_FORM_TYPES = [ 'abmeldung_student_v1' ];

	/**
	 * Holt Einsendungen eines bestimmten Users, sortiert nach Datum (neu oben).
	 *
	 * @param string[] $form_types Auf diese Typen einschränken; leer = alle.
	 */
	public function get_submissions_by_user( int $user_id, array $form_types = [] ): array {
		$where  = [ 'user_id = %d' ];
		$params = [ $user_id ];

		if ( ! empty( $form_types ) ) {
			$where[]  = 'form_type IN (' . implode( ', ', array_fill( 0, count( $form_types ), '%s' ) ) . ')';
			$params   = array_merge( $params, array_values( $form_types ) );
		}

		$where_clause = implode( ' AND ', $where );

		return $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY created_at DESC",
			...$params
		), ARRAY_A );
	}

	/**
	 * Zählt Einträge eines Formulartyps — für die Altlasten-Prüfung.
	 *
	 * Gibt bewusst nur Anzahl, IDs, Zeitstempel und Benutzer-IDs zurück, keine
	 * form_data: Dienstbefreiungen enthalten personenbezogene Angaben, und für das
	 * Aufräumen braucht es sie nicht.
	 *
	 * @return array{count:int, ids:int[], first:?string, last:?string, users:int[]}
	 */
	public function inspect_form_type( string $form_type ): array {
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT id, user_id, created_at FROM {$this->table_name}
			 WHERE form_type = %s ORDER BY created_at ASC",
			$form_type
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return [ 'count' => 0, 'ids' => [], 'first' => null, 'last' => null, 'users' => [] ];
		}

		return [
			'count' => count( $rows ),
			'ids'   => array_map( 'intval', array_column( $rows, 'id' ) ),
			'first' => (string) $rows[0]['created_at'],
			'last'  => (string) $rows[ count( $rows ) - 1 ]['created_at'],
			'users' => array_values( array_unique( array_map( 'intval', array_column( $rows, 'user_id' ) ) ) ),
		];
	}

	/**
	 * Entfernt alle Einträge eines Formulartyps. Ausschliesslich für Altlasten gedacht,
	 * die durch einen geänderten Ablauf entstanden sind — der Aufrufer muss die
	 * Rückfrage stellen, hier wird ohne Nachfrage gelöscht.
	 *
	 * @return int Anzahl der gelöschten Zeilen.
	 */
	public function delete_by_form_type( string $form_type ): int {
		$deleted = $this->db->delete( $this->table_name, [ 'form_type' => $form_type ], [ '%s' ] );

		return false === $deleted ? 0 : (int) $deleted;
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
	 * Findet die Einsendung, die aus derselben Formularsitzung stammt.
	 *
	 * Das PDF öffnet sich in einem neuen Fenster, das Formular bleibt stehen und kennt
	 * die frisch vergebene ID nicht. Ohne diesen Abgleich legte jedes erneute Erzeugen
	 * eine weitere Einsendung an. Der Token steht im form_data-JSON; die Suche ist auf
	 * Benutzer und Formulartyp eingegrenzt und damit klein.
	 */
	public function find_id_by_client_token( int $user_id, string $form_type, string $token ): int {
		if ( $user_id <= 0 || '' === $token ) {
			return 0;
		}

		$like = '%' . $this->db->esc_like( '"client_token":"' . $token . '"' ) . '%';

		return (int) $this->db->get_var( $this->db->prepare(
			"SELECT id FROM {$this->table_name} WHERE user_id = %d AND form_type = %s AND form_data LIKE %s ORDER BY id DESC LIMIT 1",
			$user_id,
			$form_type,
			$like
		) );
	}

	/**
	 * Belegung der Nachschreibtermine einer Art: je Datum die Zahl der Anmeldungen und der
	 * angemeldeten Schüler*innen. Gibt bewusst nur Zahlen zurück, keine Namen.
	 *
	 * Termin-Art und -Datum stehen nur im form_data-JSON. Das Model schreibt termin_typ
	 * und termin_datum direkt hintereinander, darauf grenzt das LIKE vor; ausgezählt wird
	 * nach dem Dekodieren, damit ein zufälliger Treffer im Freitext nicht mitzählt.
	 *
	 * @param int $exclude_id Diese Anmeldung nicht mitzählen (beim Bearbeiten die eigene).
	 * @return array<string,array{anmeldungen:int,schueler:int}> Schlüssel = Datum (Y-m-d)
	 */
	public function get_nachschreib_belegung( string $typ, int $exclude_id = 0 ): array {
		$like = '%' . $this->db->esc_like( '"termin_typ":' . wp_json_encode( $typ ) ) . '%';
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT id, form_data FROM {$this->table_name} WHERE form_type = %s AND id <> %d AND form_data LIKE %s",
			'nachschreib_anmeldung_v1',
			$exclude_id,
			$like
		), ARRAY_A ) ?: [];

		$out = [];
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['form_data'], true );
			if ( ! is_array( $data ) || ( $data['termin_typ'] ?? '' ) !== $typ || empty( $data['termin_datum'] ) ) {
				continue;
			}
			$datum = (string) $data['termin_datum'];
			$out[ $datum ]['anmeldungen'] = ( $out[ $datum ]['anmeldungen'] ?? 0 ) + 1;
			$out[ $datum ]['schueler']    = ( $out[ $datum ]['schueler'] ?? 0 ) + count( (array) ( $data['rows'] ?? [] ) );
		}
		return $out;
	}

	/**
	 * Alle Anmeldungen zu einem Nachschreibtermin (für die Buchungsübersicht der
	 * Terminverwaltung), älteste zuerst - die Reihenfolge der Eingänge zählt laut Vorlage.
	 *
	 * @return array<int,array{id:int,user_id:int,created_at:string,updated_at:string,form_data:array}>
	 */
	public function get_nachschreib_anmeldungen( string $typ, string $datum ): array {
		$like = '%' . $this->db->esc_like( '"termin_datum":' . wp_json_encode( $datum ) ) . '%';
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT id, user_id, created_at, updated_at, form_data FROM {$this->table_name}
			 WHERE form_type = %s AND form_data LIKE %s ORDER BY created_at ASC, id ASC",
			'nachschreib_anmeldung_v1',
			$like
		), ARRAY_A ) ?: [];

		$out = [];
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['form_data'], true );
			// Exakt prüfen: das LIKE grenzt nur vor (gleiches Datum kann zu anderer Art gehören).
			if ( ! is_array( $data ) || ( $data['termin_typ'] ?? '' ) !== $typ || ( $data['termin_datum'] ?? '' ) !== $datum ) {
				continue;
			}
			$out[] = [
				'id'         => (int) $row['id'],
				'user_id'    => (int) $row['user_id'],
				'created_at' => (string) $row['created_at'],
				'updated_at' => (string) $row['updated_at'],
				'form_data'  => $data,
			];
		}
		return $out;
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