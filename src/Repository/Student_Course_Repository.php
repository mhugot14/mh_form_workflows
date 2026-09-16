<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Student_Course_Repository
 *
 * Liest die klassenuebergreifenden Kursbelegungen eines Schuelers aus der Tabelle
 * des webuntisAnalyser-Plugins.
 *
 * Verknuepft wird ueber den Schild-Schluessel, NICHT ueber die wu_id: die wird beim
 * CSV-Import der Schueler fortlaufend neu vergeben und wuerde die Zuordnung nach
 * jedem Reimport zerstoeren.
 */
class Student_Course_Repository {

	private string $table_name;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'wa_student_courses';
	}

	/**
	 * Holt die Kursbelegungen eines Schuelers.
	 *
	 * @param string $schild_id Schild-Schluessel des Schuelers.
	 * @return array Liste mit subject_short, course_name und teacher_short.
	 */
	public function get_courses_for_student( string $schild_id ): array {
		if ( '' === $schild_id ) {
			return [];
		}
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		$query = $this->db->prepare(
			"SELECT subject_short, course_name, teacher_short
             FROM {$this->table_name}
             WHERE schild_id = %s
             ORDER BY subject_short ASC, course_name ASC",
			$schild_id
		);

		return $this->db->get_results( $query, ARRAY_A );
	}
}
