<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Teacher_Account_Repository
 *
 * Liest die Verknüpfung Lehrerkürzel <-> WordPress-Benutzer <-> Benachrichtigungsadresse
 * aus der Tabelle wa_teacher_accounts des webuntisAnalyser-Plugins.
 *
 * Der Schlüssel ist bewusst das Kürzel und nicht die wu_id: WebUntis kann zum
 * Schuljahreswechsel neue IDs vergeben, das Kürzel bleibt. Die Fächertabelle im
 * Abgangsformular speichert ohnehin das Kürzel (subj_teacher).
 */
class Teacher_Account_Repository {

	private string $table_name;
	private string $teachers_table;

	public function __construct( private wpdb $db ) {
		$this->table_name     = $this->db->prefix . 'wa_teacher_accounts';
		$this->teachers_table = $this->db->prefix . 'wa_teachers';
	}

	private function table_exists(): bool {
		return $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
	}

	/**
	 * Ermittelt Empfänger und Zustelladresse für ein Lehrerkürzel.
	 *
	 * Reihenfolge:
	 *   1. gepflegte Hauptadresse (notify_email)
	 *   2. E-Mail des verknüpften WordPress-Kontos — als Rückfall markiert, weil die
	 *      Microsoft-Adressen im Kollegium häufig nicht abgerufen werden
	 *   3. kein Empfänger -> null
	 *
	 * @return array{user_id:int, email:string, is_fallback:bool, name:string}|null
	 */
	public function resolve_recipient( string $kuerzel ): ?array {
		$kuerzel = trim( $kuerzel );
		if ( '' === $kuerzel || ! $this->table_exists() ) {
			return null;
		}

		$row = $this->db->get_row( $this->db->prepare(
			"SELECT kuerzel, wp_user_id, notify_email FROM {$this->table_name} WHERE kuerzel = %s LIMIT 1",
			$kuerzel
		), ARRAY_A );

		if ( null === $row ) {
			return null;
		}

		$user_id   = (int) ( $row['wp_user_id'] ?? 0 );
		$user      = $user_id > 0 ? get_userdata( $user_id ) : false;
		$user_mail = $user ? (string) $user->user_email : '';
		$name      = $user ? (string) $user->display_name : $kuerzel;

		$notify = trim( (string) ( $row['notify_email'] ?? '' ) );

		if ( '' !== $notify ) {
			return [ 'user_id' => $user_id, 'email' => $notify, 'is_fallback' => false, 'name' => $name ];
		}
		if ( '' !== $user_mail ) {
			return [ 'user_id' => $user_id, 'email' => $user_mail, 'is_fallback' => true, 'name' => $name ];
		}

		return null;
	}

	/**
	 * Zustelladresse für einen WordPress-Benutzer (z. B. die Klassenleitung eines Falls).
	 *
	 * Gleiche Regel wie resolve_recipient(): Eine gepflegte Hauptadresse (notify_email)
	 * an einem seiner Kürzel hat Vorrang, erst dann gilt die Konto-Adresse. Vorher gingen
	 * alle Mails an die Klassenleitung fest an die Konto-Adresse - die Hauptadresse
	 * wurde nur für Fachlehrkräfte beachtet.
	 */
	public function resolve_email_for_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		if ( $this->table_exists() ) {
			$notify = (string) $this->db->get_var( $this->db->prepare(
				"SELECT notify_email FROM {$this->table_name}
				 WHERE wp_user_id = %d AND notify_email IS NOT NULL AND notify_email <> ''
				 ORDER BY kuerzel ASC LIMIT 1",
				$user_id
			) );
			if ( '' !== trim( $notify ) ) {
				return trim( $notify );
			}
		}

		$user = get_userdata( $user_id );

		return $user ? (string) $user->user_email : '';
	}

	/**
	 * Alle Kürzel, die zu einem WordPress-Benutzer gehören. Eine Lehrkraft kann in
	 * WebUntis theoretisch mehrere Kürzel führen, deshalb eine Liste.
	 *
	 * @return string[]
	 */
	public function get_kuerzel_for_user( int $user_id ): array {
		if ( $user_id <= 0 || ! $this->table_exists() ) {
			return [];
		}

		return array_map( 'strval', $this->db->get_col( $this->db->prepare(
			"SELECT kuerzel FROM {$this->table_name} WHERE wp_user_id = %d",
			$user_id
		) ) );
	}

	/**
	 * Klartextname zu einem Kürzel für die Anzeige in Listen und Mails.
	 */
	public function get_display_name( string $kuerzel ): string {
		$kuerzel = trim( $kuerzel );
		if ( '' === $kuerzel ) {
			return '';
		}
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->teachers_table}'" ) !== $this->teachers_table ) {
			return $kuerzel;
		}

		$row = $this->db->get_row( $this->db->prepare(
			"SELECT fore_name, long_name, title FROM {$this->teachers_table} WHERE name = %s AND is_active = 1 LIMIT 1",
			$kuerzel
		), ARRAY_A );

		if ( null === $row ) {
			return $kuerzel;
		}

		$full = trim( ( $row['title'] ?? '' ) . ' ' . ( $row['fore_name'] ?? '' ) . ' ' . ( $row['long_name'] ?? '' ) );

		return '' !== $full ? $full : $kuerzel;
	}
}
