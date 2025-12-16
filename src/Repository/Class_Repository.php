<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

class Class_Repository {

	private string $table_name;

	public function __construct( private wpdb $db ) {
		// Zugriff auf die Tabelle des Webuntis-Plugins!
		$this->table_name = $this->db->prefix . 'wa_classes';
	}

	/**
	 * Holt alle Klassen, die aktiv sind und einen Klassenlehrer haben.
	 * Damit filtern wir z.B. "DIENST" oder Raum-Reservierungen raus.
	 *
	 * @return array
	 */
	public function get_real_classes(): array {
		// Sicherheitscheck: Existiert die Tabelle überhaupt?
		if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
			return [];
		}

		// Wir filtern: Muss aktiv sein UND muss einen Lehrer haben.
		// (Je nach Import ist ein leerer Lehrer NULL oder ein leerer String)
		$query = "SELECT * 
                  FROM {$this->table_name} 
                  WHERE is_active = 1 
                  AND teacher_1 != '' 
                  AND teacher_1 IS NOT NULL
                  ORDER BY name ASC";

		return $this->db->get_results( $query, ARRAY_A );
	}
}