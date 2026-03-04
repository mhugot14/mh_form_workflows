<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Class_Repository
 * 
 * Liest Klassendaten aus der Tabelle des webuntisAnalyser Plugins.
 */
class Class_Repository {

	private string $table_name;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'wa_classes';
	}

	/**
	 * Holt alle Klassen, die aktiv sind und einen Klassenlehrer haben.
	 * Inklusive wu_id für den AJAX-Match und is_fulltime für die Formular-Logik.
	 *
	 * @return array
	 */
	public function get_real_classes(): array {
		// Sicherheitscheck: Existiert die Tabelle?
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		// Wir holen wu_id (für Schüler-Match), name (Anzeige) und is_fulltime (Logik)
		$query = "SELECT wu_id, name, is_fulltime 
                  FROM {$this->table_name} 
                  WHERE is_active = 1 
                  AND teacher_1 != '' 
                  AND teacher_1 IS NOT NULL
                  ORDER BY name ASC";

		return $this->db->get_results( $query, ARRAY_A );
	}
}