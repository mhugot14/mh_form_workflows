<?php
/**
 * Plugin Name: MH Form Workflows
 * Description: Digitalisierte Formularprozesse mit PDF-Generierung.
 * Version: 1.2.1
 * Author: Michael Hugot
 * Text Domain: mh-form-workflows
 * Requires PHP: 8.0
 */

declare(strict_types=1);

// KORREKTUR: Kein Namespace in der Hauptdatei, da sie im Root liegt.
// namespace Mh\FormWorkflows;  <-- Entfernen

// Abbrechen, wenn direkt aufgerufen.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Autoloader einbinden.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Wir importieren die Klassen aus unserem src-Ordner (Namespace muss hier angegeben werden)
use Mh\FormWorkflows\Setup\Activator;
use Mh\FormWorkflows\Setup\Plugin_Bootstrap;

/**
 * Konstanten für Pfade definieren.
 */
define( 'MH_FW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MH_FW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MH_FW_VERSION', '1.2.0' );
define( 'MH_FW_DB_VERSION', '3' );

/**
 * Aktivierungs-Hook: Tabellen erstellen.
 */
register_activation_hook( __FILE__, function () {
	Activator::activate();
} );

/**
 * Schema-Migrationen auch außerhalb eines manuellen Reaktivierens ausführen
 * (z. B. nach einem Deploy, bei dem das Plugin nicht neu aktiviert wird).
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'mh_fw_db_version' ) !== MH_FW_DB_VERSION ) {
		Activator::activate();
		update_option( 'mh_fw_db_version', MH_FW_DB_VERSION );
	}
}, 5 );

/**
 * Plugin Bootstrapping.
 */
// KORREKTUR: Funktionsname nun im globalen Scope (da Namespace weg ist)
function mh_fw_run_plugin(): void {
	$plugin = new Plugin_Bootstrap();
	$plugin->init();
}

// KORREKTUR: Aufruf der globalen Funktion (ohne Namespace-Prefix im String)
add_action( 'plugins_loaded', 'mh_fw_run_plugin' );