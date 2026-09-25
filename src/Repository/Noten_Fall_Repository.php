<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Noten_Fall_Repository
 *
 * Verwaltet die digitale Noteneinsammlung einer Ausschulung in der bestehenden Tabelle
 * mh_form_submissions (form_type = 'ausschulung_noten_v1') — dasselbe Muster wie
 * Absentismus_Fall_Repository: ein Fall ist eine Zeile, deren form_data-JSON die
 * einzelnen Fächer ("items") trägt.
 *
 * Ein Item entspricht genau einer Fächerzeile des Abgangsformulars und damit genau
 * einer Note, die eine Fachlehrkraft beisteuern soll.
 */
class Noten_Fall_Repository {

	private const FORM_TYPE = 'ausschulung_noten_v1';

	private string $table_name;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'mh_form_submissions';
	}

	/**
	 * Legt einen Fall an.
	 *
	 * @param array $meta  submission_id, student_wu_id, lastname, firstname,
	 *                     class_wu_id, class_name, owner_user_id
	 * @param array $items Je Fach: subject, teacher_kuerzel, recipient_user_id,
	 *                     recipient_email, is_fallback
	 */
	public function create_case( array $meta, array $items, int $created_by ): int {
		$now = current_time( 'mysql' );

		// Ein Fall bildet IMMER die komplette Fächerliste ab, auch die Fächer, deren Note
		// die Klassenleitung selbst einträgt. Sonst würde write_back_to_submission() beim
		// Abschluss die selbst erfassten Noten aus der Einsendung werfen - es ersetzt
		// subjects vollständig durch die Positionen des Falls.
		// Angefragt wird nur, was 'collect' markiert; alles andere kommt fertig herein und
		// hält den Fall nicht offen (count_open_items zählt nur Status != erledigt).
		$prepared = [];
		foreach ( array_values( $items ) as $idx => $item ) {
			$is_collect = ! empty( $item['collect'] );
			$grade      = (string) ( $item['grade'] ?? '' );

			$prepared[] = [
				'idx'               => $idx,
				'subject'           => (string) ( $item['subject'] ?? '' ),
				'teacher_kuerzel'   => (string) ( $item['teacher_kuerzel'] ?? '' ),
				'recipient_user_id' => (int) ( $item['recipient_user_id'] ?? 0 ),
				'recipient_email'   => (string) ( $item['recipient_email'] ?? '' ),
				'is_fallback'       => ! empty( $item['is_fallback'] ),
				'collect'           => $is_collect ? '1' : '0',
				'grade'             => $is_collect ? '' : $grade,
				'remark'            => '',
				'webuntis'          => ( ! $is_collect && ! empty( $item['webuntis'] ) ) ? '1' : '0',
				'completed'         => ( ! $is_collect && ! empty( $item['completed'] ) ) ? '1' : '0',
				'status'            => $is_collect ? 'offen' : 'erledigt',
				'entered_by'        => $is_collect ? null : $created_by,
				'entered_at'        => $is_collect ? null : $now,
				'entered_by_role'   => $is_collect ? null : 'vorab',
				'notified_at'       => null,
				'reminder_count'    => 0,
				'last_reminder_at'  => null,
				'escalated_at'      => null,
			];
		}

		$form_data = array_merge( $meta, [
			'case_status'  => 'offen',
			'started_at'   => $now,
			'completed_at' => null,
			'items'        => $prepared,
		] );

		$inserted = $this->db->insert( $this->table_name, [
			'form_type'     => self::FORM_TYPE,
			'status'        => 'offen',
			'user_id'       => $created_by,
			'student_wu_id' => (int) ( $meta['student_wu_id'] ?? 0 ),
			'form_data'     => wp_json_encode( $form_data ),
			'created_at'    => $now,
			'updated_at'    => $now,
		] );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	public function get_by_id( int $case_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d AND form_type = %s",
			$case_id,
			self::FORM_TYPE
		), ARRAY_A );

		return $this->decode_row( $row );
	}

	/**
	 * Ein offener Fall zu einer Einsendung — verhindert, dass derselbe Prozess
	 * versehentlich zweimal gestartet wird.
	 */
	public function find_open_case_by_submission( int $submission_id ): ?array {
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE form_type = %s AND status = 'offen'",
			self::FORM_TYPE
		), ARRAY_A );

		foreach ( $rows as $row ) {
			$case = $this->decode_row( $row );
			if ( (int) ( $case['form_data']['submission_id'] ?? 0 ) === $submission_id ) {
				return $case;
			}
		}

		return null;
	}

	/**
	 * @return array<int, array> Alle Fälle, ggf. gefiltert nach status.
	 */
	public function get_all_cases( array $filters = [] ): array {
		$where  = [ 'form_type = %s' ];
		$params = [ self::FORM_TYPE ];

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['owner_user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['owner_user_id'];
		}

		$where_clause = implode( ' AND ', $where );

		return array_map( [ $this, 'decode_row' ], $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY created_at DESC",
			...$params
		), ARRAY_A ) );
	}

	/**
	 * Alle Fälle, an denen ein Benutzer als Fachlehrkraft beteiligt ist — Grundlage
	 * für "Meine Noteneingaben". Die Zuordnung steht im JSON, deshalb wird nach dem
	 * Laden gefiltert (die Fallzahlen sind pro Schule klein genug dafür).
	 *
	 * @param string[] $kuerzel_list Kürzel des Benutzers, als zusätzlicher Treffer-Pfad
	 *                               falls recipient_user_id noch nicht gesetzt war.
	 * @return array<int, array{case: array, items: array}>
	 */
	public function get_cases_for_teacher( int $user_id, array $kuerzel_list = [], ?string $status = null ): array {
		$filters = [];
		if ( null !== $status ) {
			$filters['status'] = $status;
		}

		$result = [];
		foreach ( $this->get_all_cases( $filters ) as $case ) {
			$mine = [];
			foreach ( $case['form_data']['items'] ?? [] as $item ) {
				$by_user    = $user_id > 0 && (int) ( $item['recipient_user_id'] ?? 0 ) === $user_id;
				$by_kuerzel = in_array( (string) ( $item['teacher_kuerzel'] ?? '' ), $kuerzel_list, true );
				if ( $by_user || $by_kuerzel ) {
					$mine[] = $item;
				}
			}
			if ( ! empty( $mine ) ) {
				$result[] = [ 'case' => $case, 'items' => $mine ];
			}
		}

		return $result;
	}

	/**
	 * Trägt eine Note ein. Gibt false zurück, wenn der Fall oder das Item fehlt —
	 * die Berechtigungsprüfung liegt beim Controller.
	 *
	 * @param string $role 'fachlehrer' oder 'klassenlehrer' (Nachtrag durch den Klassenlehrer).
	 *                     Positionen, deren Note beim Anlegen des Falls schon feststand,
	 *                     tragen stattdessen 'vorab'.
	 */
	public function set_item_grade( int $case_id, int $idx, array $values, int $user_id, string $role ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$form_data = $case['form_data'];
		$found     = false;

		foreach ( $form_data['items'] as &$item ) {
			if ( (int) $item['idx'] !== $idx ) {
				continue;
			}
			$item['grade']           = (string) ( $values['grade'] ?? '' );
			$item['remark']          = (string) ( $values['remark'] ?? '' );
			$item['webuntis']        = ! empty( $values['webuntis'] ) ? '1' : '0';
			$item['completed']       = ! empty( $values['completed'] ) ? '1' : '0';
			$item['status']          = '' !== $item['grade'] ? 'erledigt' : 'offen';
			$item['entered_by']      = $user_id;
			$item['entered_at']      = current_time( 'mysql' );
			$item['entered_by_role'] = $role;
			$found                   = true;
			break;
		}
		unset( $item );

		if ( ! $found ) {
			return false;
		}

		return $this->persist( $case_id, $form_data );
	}

	/**
	 * Hält fest, dass eine Einladung oder Erinnerung rausgegangen ist.
	 */
	public function mark_notified( int $case_id, int $idx, bool $is_reminder ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$form_data = $case['form_data'];
		$now       = current_time( 'mysql' );

		foreach ( $form_data['items'] as &$item ) {
			if ( (int) $item['idx'] !== $idx ) {
				continue;
			}
			if ( $is_reminder ) {
				$item['reminder_count']   = (int) ( $item['reminder_count'] ?? 0 ) + 1;
				$item['last_reminder_at'] = $now;
			} else {
				$item['notified_at'] = $now;
			}
			break;
		}
		unset( $item );

		return $this->persist( $case_id, $form_data );
	}

	public function mark_escalated( int $case_id, int $idx ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$form_data = $case['form_data'];
		foreach ( $form_data['items'] as &$item ) {
			if ( (int) $item['idx'] === $idx ) {
				$item['escalated_at'] = current_time( 'mysql' );
				break;
			}
		}
		unset( $item );

		return $this->persist( $case_id, $form_data );
	}

	public function count_open_items( array $case ): int {
		$open = 0;
		foreach ( $case['form_data']['items'] ?? [] as $item ) {
			if ( 'erledigt' !== ( $item['status'] ?? 'offen' ) ) {
				$open++;
			}
		}

		return $open;
	}

	public function close_case( int $case_id ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$form_data                 = $case['form_data'];
		$form_data['case_status']  = 'abgeschlossen';
		$form_data['completed_at'] = current_time( 'mysql' );

		return $this->persist( $case_id, $form_data, [ 'status' => 'abgeschlossen' ] );
	}

	/**
	 * Baut aus den Items die subjects-Struktur des Abgangsformulars. Damit lassen sich
	 * die eingesammelten Noten in die Einsendung zurückschreiben — pdf-abmeldung.php
	 * und pdf-protocol.php rendern direkt daraus, es braucht keinen eigenen PDF-Pfad.
	 */
	public function build_subjects_from_case( array $case ): array {
		$subjects = [];
		foreach ( $case['form_data']['items'] ?? [] as $item ) {
			$subjects[] = [
				'name'      => (string) ( $item['subject'] ?? '' ),
				'teacher'   => (string) ( $item['teacher_kuerzel'] ?? '' ),
				'grade'     => (string) ( $item['grade'] ?? '' ),
				// Noch offene Positionen bleiben im Formular als "angefragt" markiert,
				// damit ein zwischenzeitlicher Blick ins Formular den Stand zeigt.
				'collect'   => ( 'erledigt' === ( $item['status'] ?? 'offen' ) ) ? '0' : '1',
				'webuntis'  => (string) ( $item['webuntis'] ?? '0' ),
				'completed' => (string) ( $item['completed'] ?? '0' ),
			];
		}

		return $subjects;
	}

	private function persist( int $case_id, array $form_data, array $extra_columns = [] ): bool {
		$columns = array_merge( $extra_columns, [
			'form_data'  => wp_json_encode( $form_data ),
			'updated_at' => current_time( 'mysql' ),
		] );

		$formats = array_map( static fn( $value ): string => is_int( $value ) ? '%d' : '%s', $columns );

		$updated = $this->db->update(
			$this->table_name,
			$columns,
			[ 'id' => $case_id, 'form_type' => self::FORM_TYPE ],
			array_values( $formats ),
			[ '%d', '%s' ]
		);

		return false !== $updated;
	}

	private function decode_row( ?array $row ): ?array {
		if ( null === $row ) {
			return null;
		}
		if ( isset( $row['form_data'] ) && is_string( $row['form_data'] ) ) {
			$row['form_data'] = json_decode( $row['form_data'], true ) ?: [];
		}

		return $row;
	}
}
