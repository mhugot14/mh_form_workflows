<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Setup;

/**
 * Class Activator
 *
 * Kümmert sich um Tasks, die bei der Plugin-Aktivierung laufen müssen (DB Tabellen).
 */
class Activator {

	/**
	 * Erstellt die Datenbanktabellen.
	 *
	 * @return void
	 */
	public static function activate(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'mh_form_submissions';
		$charset_collate = $wpdb->get_charset_collate();

		// Wir speichern Formulardaten erst mal als JSON Blob für Flexibilität,
		// plus wichtige Metadaten in eigenen Spalten für Performance/Filterung.
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			form_type varchar(50) NOT NULL,
			status varchar(20) DEFAULT 'draft' NOT NULL,
			user_id bigint(20) NOT NULL,
			form_data longtext NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY form_type (form_type),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}