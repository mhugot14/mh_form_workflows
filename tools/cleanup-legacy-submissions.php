<?php
/**
 * Altlasten in mh_form_submissions prüfen und entfernen.
 *
 * Hintergrund: Dienstbefreiungen (service_leave_v1) werden nicht gespeichert, das PDF
 * wird direkt ausgeliefert. In älteren Plugin-Versionen war das anders, weshalb in
 * bestehenden Installationen noch Zeilen dieses Typs liegen können. Sie erscheinen in
 * keiner Liste mehr und sind deshalb über die Oberfläche nicht erreichbar.
 *
 * Ohne Shell-Zugang geht dasselbe über MH Formulare -> Einstellungen -> Wartung.
 *
 * Aufruf (aus dem Plugin-Verzeichnis):
 *   php tools/cleanup-legacy-submissions.php            # nur prüfen
 *   php tools/cleanup-legacy-submissions.php --delete   # tatsächlich löschen
 *
 * Es werden bewusst keine Inhalte ausgegeben, nur Anzahl, IDs und Zeitstempel.
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit( "Dieses Skript läuft nur auf der Kommandozeile.\n" );
}

$do_delete = in_array( '--delete', $argv, true );

// wp-load.php suchen: vom Plugin-Verzeichnis nach oben bis zur WordPress-Wurzel.
$dir      = __DIR__;
$wp_load  = '';
for ( $i = 0; $i < 8; $i++ ) {
	$dir = dirname( $dir );
	if ( file_exists( $dir . '/wp-load.php' ) ) {
		$wp_load = $dir . '/wp-load.php';
		break;
	}
}
if ( '' === $wp_load ) {
	exit( "wp-load.php nicht gefunden. Bitte aus dem Plugin-Verzeichnis einer WordPress-Installation starten.\n" );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

global $wpdb;

$repo   = new \Mh\FormWorkflows\Repository\Submission_Repository( $wpdb );
$legacy = 'service_leave_v1';
$info   = $repo->inspect_form_type( $legacy );

echo "Tabelle: {$wpdb->prefix}mh_form_submissions\n";
echo "Gesucht: form_type = '{$legacy}' (Dienstbefreiungen werden nicht mehr gespeichert)\n\n";

if ( 0 === $info['count'] ) {
	exit( "Keine Altlasten gefunden. Nichts zu tun.\n" );
}

printf( "Gefunden:   %d Zeile(n)\n", $info['count'] );
printf( "IDs:        %s\n", implode( ', ', $info['ids'] ) );
printf( "Zeitraum:   %s bis %s\n", $info['first'], $info['last'] );
printf( "Benutzer:   %s\n\n", implode( ', ', $info['users'] ) );

if ( ! $do_delete ) {
	echo "Probelauf - es wurde nichts verändert.\n";
	echo "Zum Löschen erneut mit --delete aufrufen. Vorher bitte ein Backup der Tabelle ziehen.\n";
	exit( 0 );
}

echo "Löschen? Es gibt danach kein Zurück. Zum Bestätigen 'ja' eingeben: ";
$answer = trim( (string) fgets( STDIN ) );

if ( 'ja' !== strtolower( $answer ) ) {
	exit( "Abgebrochen, nichts verändert.\n" );
}

$deleted = $repo->delete_by_form_type( $legacy );
printf( "%d Zeile(n) gelöscht.\n", $deleted );

$rest = $repo->inspect_form_type( $legacy );
printf( "Verbleibend: %d\n", $rest['count'] );
