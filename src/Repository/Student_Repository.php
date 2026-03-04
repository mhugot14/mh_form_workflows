<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Student_Repository
 * 
 * Liest Schülerdaten aus der Tabelle des webuntisAnalyser Plugins.
 */
class Student_Repository {

	private string $table_name;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'wa_students';
	}

	/**
	 * Holt alle aktiven Schüler einer bestimmten Klasse anhand der WebUntis-Klassen-ID.
	 *
	 * @param int $class_wu_id Die WebUntis-ID der Klasse.
	 * @return array
	 */
	public function get_students_by_class( int $class_wu_id ): array {
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		$query = $this->db->prepare(
			"SELECT wu_id, name, fore_name 
             FROM {$this->table_name} 
             WHERE class_wu_id = %d 
             AND is_active = 1 
             ORDER BY name ASC, fore_name ASC",
			$class_wu_id
		);

		return $this->db->get_results( $query, ARRAY_A );
	}

	/**
	 * Holt einen einzelnen Schüler anhand seiner WebUntis-ID.
	 * Nützlich, um nach dem Absenden die Klarnamen für das PDF zu validieren.
	 *
	 * @param int $student_wu_id
	 * @return array|null
	 */
	public function get_student_by_wu_id( int $student_wu_id ): ?array {
		$query = $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE wu_id = %d LIMIT 1",
			$student_wu_id
		);

		return $this->db->get_row( $query, ARRAY_A );
	}
}