<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

class Teacher_Repository {

	private string $table_name;

	public function __construct( private wpdb $db ) {
		// Zugriff auf die Tabelle des Webuntis-Plugins
		$this->table_name = $this->db->prefix . 'wa_teachers';
	}

	/**
	 * Holt alle aktiven Lehrer (Kürzel + Name).
	 *
	 * @return array
	 */
	public function get_all_teachers(): array {
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		// Wir holen name (Kürzel) und long_name (Nachname)
		$query = "SELECT id, name, long_name, fore_name, title 
                  FROM {$this->table_name} 
                  WHERE is_active = 1 
                  ORDER BY name ASC";

		return $this->db->get_results( $query, ARRAY_A );
	}
}