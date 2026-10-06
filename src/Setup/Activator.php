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
			student_wu_id bigint(20) DEFAULT NULL,
			archived_at datetime DEFAULT NULL,
			form_data longtext NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY form_type (form_type),
			KEY user_id (user_id),
			KEY student_wu_id (student_wu_id)
		) $charset_collate;";

		// Nachschreibtermine: nur Abweichungen von den Katalog-Vorgaben (siehe
		// Nachschreib_Termin_Repository). Eine Zeile je Art und Datum.
		$termine_table = $wpdb->prefix . 'mh_nachschreib_termine';
		$sql_termine   = "CREATE TABLE $termine_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			typ varchar(20) NOT NULL,
			datum date NOT NULL,
			aktiv tinyint(1) DEFAULT 1 NOT NULL,
			zeit_von varchar(5) DEFAULT '' NOT NULL,
			zeit_bis varchar(5) DEFAULT '' NOT NULL,
			raum varchar(100) DEFAULT '' NOT NULL,
			hinweis varchar(255) DEFAULT '' NOT NULL,
			kontingent smallint(5) unsigned DEFAULT NULL,
			updated_by bigint(20) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY typ_datum (typ,datum)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_termine );
	}
}