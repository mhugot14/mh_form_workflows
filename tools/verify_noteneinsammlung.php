<?php
/**
 * Regressionstest der digitalen Noteneinsammlung — ohne Browser, ohne Mailversand.
 *
 * Aufruf (aus dem Plugin-Verzeichnis):
 *   php tools/verify_noteneinsammlung.php
 *
 * Mails werden über den pre_wp_mail-Filter abgefangen statt gesendet. Es werden
 * ausschliesslich erfundene Datensätze angelegt (Präfix __T…__) und am Ende wieder
 * entfernt — echte Schüler- und Lehrerdaten werden weder gelesen noch ausgegeben.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( "Dieses Skript läuft nur auf der Kommandozeile.\n" );
}

$wp_load = realpath( __DIR__ . '/../../../../wp-load.php' );
if ( ! $wp_load ) {
	exit( "wp-load.php nicht gefunden — läuft nur innerhalb einer WordPress-Installation.\n" );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$fails = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $fails;
	if ( ! $ok ) { $fails++; }
	echo ( $ok ? '[OK]    ' : '[FEHLER]' ) . " $label" . ( '' !== $detail ? "  -> $detail" : '' ) . "\n";
}

// Mails abfangen statt senden.
$GLOBALS['mh_sent_mails'] = [];
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$GLOBALS['mh_sent_mails'][] = $atts;
	return true;
}, 10, 2 );

echo "=== 1. Schema ===\n";
\webuntisAnalyser\Plugin_Helpers::update_db_schema();
$tbl_acc = $wpdb->prefix . 'wa_teacher_accounts';
check( 'Tabelle wa_teacher_accounts existiert', $wpdb->get_var( "SHOW TABLES LIKE '$tbl_acc'" ) === $tbl_acc );
check( 'UNIQUE-Index auf kuerzel', ! empty( $wpdb->get_results( "SHOW INDEX FROM $tbl_acc WHERE Key_name = 'kuerzel' AND Non_unique = 0" ) ) );
check( 'DB_VERSION ist 1.4', get_option( 'mh_wa_db_version' ) === '1.4', (string) get_option( 'mh_wa_db_version' ) );

echo "\n=== 2. Testbenutzer + Zuordnungen ===\n";
$mk_user = function ( string $login, string $mail, string $name ) {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$u = get_user_by( 'ID', wp_insert_user( [
			'user_login' => $login, 'user_pass' => wp_generate_password(),
			'user_email' => $mail, 'display_name' => $name,
		] ) );
	}
	return $u;
};
$u_full  = $mk_user( 'mh_test_full', 'mh_test_full@example.test', 'Test Vollstaendig' );
$u_fb    = $mk_user( 'mh_test_fallback', 'mh_test_fallback@example.test', 'Test Fallback' );
$u_owner = $mk_user( 'mh_test_owner', 'mh_test_owner@example.test', 'Test Klassenlehrer' );

foreach ( [ '__TFULL__', '__TFB__', '__TNONE__' ] as $k ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $tbl_acc WHERE kuerzel = %s", $k ) );
}
$wpdb->insert( $tbl_acc, [ 'kuerzel' => '__TFULL__', 'wp_user_id' => $u_full->ID, 'notify_email' => 'haupt@lebk-muenster.test', 'updated_at' => current_time( 'mysql' ) ] );
$wpdb->insert( $tbl_acc, [ 'kuerzel' => '__TFB__',   'wp_user_id' => $u_fb->ID,   'notify_email' => '', 'updated_at' => current_time( 'mysql' ) ] );
$wpdb->insert( $tbl_acc, [ 'kuerzel' => '__TNONE__', 'wp_user_id' => 0,           'notify_email' => '', 'updated_at' => current_time( 'mysql' ) ] );

$account = new \Mh\FormWorkflows\Repository\Teacher_Account_Repository( $wpdb );

$r1 = $account->resolve_recipient( '__TFULL__' );
check( 'Hauptadresse gewinnt', $r1 && 'haupt@lebk-muenster.test' === $r1['email'] && false === $r1['is_fallback'] );
$r2 = $account->resolve_recipient( '__TFB__' );
check( 'Fallback auf WP-Kontoadresse', $r2 && 'mh_test_fallback@example.test' === $r2['email'] && true === $r2['is_fallback'] );
check( 'Ohne Adresse -> null', null === $account->resolve_recipient( '__TNONE__' ) );
check( 'Unbekanntes Kuerzel -> null', null === $account->resolve_recipient( '__GIBTSNICHT__' ) );
check( 'Leeres Kuerzel -> null', null === $account->resolve_recipient( '' ) );
check( 'get_kuerzel_for_user findet Kuerzel', in_array( '__TFULL__', $account->get_kuerzel_for_user( (int) $u_full->ID ), true ) );

echo "\n=== 3. Sync-Festigkeit (statisch) ===\n";
$client_src = file_get_contents( __DIR__ . '/../../webuntisAnalyser/includes/controller/Webuntis_client.php' );
check( 'sync_teachers_to_db fasst wa_teacher_accounts nicht an', is_string( $client_src ) && false === strpos( $client_src, 'wa_teacher_accounts' ) );

echo "\n=== 4. Fall-Lebenszyklus ===\n";
$sub_repo   = new \Mh\FormWorkflows\Repository\Submission_Repository( $wpdb );
$case_repo  = new \Mh\FormWorkflows\Repository\Noten_Fall_Repository( $wpdb );
$mail       = new \Mh\FormWorkflows\Service\Mail_Service();
$reminder   = new \Mh\FormWorkflows\Service\Reminder_Service( $case_repo, $account, $mail );
$controller = new \Mh\FormWorkflows\Controller\Noten_Controller( $case_repo, $account, $sub_repo, $mail, $reminder );

$submission_id = $sub_repo->create( [
	'form_type' => 'abmeldung_student_v1', 'status' => 'submitted', 'user_id' => (int) $u_owner->ID,
	'form_data' => [
		'lastname' => 'Testmann', 'firstname' => 'Max', 'class_name' => 'WG12A',
		'prot_remarks' => 'Bestehende Bemerkung.',
		'subjects' => [
			[ 'name' => 'D', 'teacher' => '__TFULL__', 'grade' => '', 'webuntis' => '0', 'completed' => '0' ],
			[ 'name' => 'E', 'teacher' => '__TFB__',   'grade' => '', 'webuntis' => '0', 'completed' => '0' ],
		],
	],
] );
check( 'Test-Einsendung angelegt', $submission_id > 0 );

$case_id = $case_repo->create_case( [
	'submission_id' => $submission_id, 'student_wu_id' => 999999,
	'lastname' => 'Testmann', 'firstname' => 'Max',
	'class_wu_id' => 0, 'class_name' => 'WG12A', 'owner_user_id' => (int) $u_owner->ID,
], [
	[ 'subject' => 'D', 'teacher_kuerzel' => '__TFULL__', 'recipient_user_id' => (int) $u_full->ID, 'recipient_email' => 'haupt@lebk-muenster.test', 'is_fallback' => false ],
	[ 'subject' => 'E', 'teacher_kuerzel' => '__TFB__',   'recipient_user_id' => (int) $u_fb->ID,   'recipient_email' => 'mh_test_fallback@example.test', 'is_fallback' => true ],
], (int) $u_owner->ID );
check( 'Fall angelegt', $case_id > 0 );

$case = $case_repo->get_by_id( $case_id );
check( 'Zwei offene Items', 2 === $case_repo->count_open_items( $case ) );
check( 'Doppelstart wird erkannt', null !== $case_repo->find_open_case_by_submission( $submission_id ) );

$case_repo->set_item_grade( $case_id, 0, [ 'grade' => '2', 'remark' => '', 'webuntis' => true, 'completed' => false ], (int) $u_full->ID, 'fachlehrer' );
$case = $case_repo->get_by_id( $case_id );
check( 'Nach erster Note noch 1 offen', 1 === $case_repo->count_open_items( $case ) );
check( 'Fall noch offen', false === $controller->maybe_complete_case( $case_id ) );

$case_repo->set_item_grade( $case_id, 1, [ 'grade' => 'NB', 'remark' => 'Zu wenige Leistungsnachweise.', 'webuntis' => false, 'completed' => false ], (int) $u_owner->ID, 'klassenlehrer' );
check( 'Fall wird abgeschlossen', true === $controller->maybe_complete_case( $case_id ) );
$case = $case_repo->get_by_id( $case_id );
check( 'Status abgeschlossen', 'abgeschlossen' === $case['status'] );

$entry = $sub_repo->get_by_id( $submission_id );
$subj  = $entry['form_data']['subjects'] ?? [];
check( 'Noten in der Einsendung', '2' === ( $subj[0]['grade'] ?? '' ) && 'NB' === ( $subj[1]['grade'] ?? '' ) );
check( 'Haken uebernommen', '1' === ( $subj[0]['webuntis'] ?? '' ) );
$remarks = (string) ( $entry['form_data']['prot_remarks'] ?? '' );
check( 'NB-Begruendung im Protokoll ergaenzt',
	false !== strpos( $remarks, 'Zu wenige Leistungsnachweise' ) && false !== strpos( $remarks, 'Bestehende Bemerkung' ) );
check( 'Nachtrag-Rolle vermerkt', 'klassenlehrer' === ( $case['form_data']['items'][1]['entered_by_role'] ?? '' ) );

echo "\n=== 5. Mails ===\n";
$GLOBALS['mh_sent_mails'] = [];
$case2_sub = $sub_repo->create( [
	'form_type' => 'abmeldung_student_v1', 'status' => 'submitted', 'user_id' => (int) $u_owner->ID,
	'form_data' => [ 'lastname' => 'Mailtest', 'firstname' => 'Mia', 'class_name' => 'WG12A', 'subjects' => [] ],
] );
$case2_id = $case_repo->create_case( [
	'submission_id' => $case2_sub, 'student_wu_id' => 999998, 'lastname' => 'Mailtest', 'firstname' => 'Mia',
	'class_wu_id' => 0, 'class_name' => 'WG12A', 'owner_user_id' => (int) $u_owner->ID,
], [
	[ 'subject' => 'D', 'teacher_kuerzel' => '__TFULL__', 'recipient_user_id' => (int) $u_full->ID, 'recipient_email' => 'haupt@lebk-muenster.test', 'is_fallback' => false ],
], (int) $u_owner->ID );

$case2 = $case_repo->get_by_id( $case2_id );
$mail->send_invitation( 'haupt@lebk-muenster.test', 'Test Vollstaendig', $case2, $case2['form_data']['items'][0], $reminder->entry_link( $case2_id, 0 ) );

check( 'Einladung wurde versendet', 1 === count( $GLOBALS['mh_sent_mails'] ) );
$m = $GLOBALS['mh_sent_mails'][0] ?? [];
check( 'Empfaenger korrekt', 'haupt@lebk-muenster.test' === ( $m['to'] ?? '' ) );
check( 'Schuelername im Betreff', false !== strpos( (string) ( $m['subject'] ?? '' ), 'Mailtest' ) );
check( 'Link auf die Eingabemaske enthalten', false !== strpos( (string) ( $m['message'] ?? '' ), 'mh_noten_id=' . $case2_id ) );
check( 'Keine Note im Mailtext', ! preg_match( '/Note:\s*[1-6]|Note\s+[1-6]\b/u', (string) ( $m['message'] ?? '' ) ) );

echo "\n=== 6. Erinnerungen / Cron ===\n";
check( 'Cron-Hook registriert', false !== wp_next_scheduled( \Mh\FormWorkflows\Service\Reminder_Service::CRON_HOOK ) );

$backdate = function ( int $case_id, string $field ) use ( $case_repo, $wpdb ) {
	$c  = $case_repo->get_by_id( $case_id );
	$fd = $c['form_data'];
	$fd['items'][0][ $field ] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 10 * DAY_IN_SECONDS );
	$wpdb->update( $wpdb->prefix . 'mh_form_submissions', [ 'form_data' => wp_json_encode( $fd ) ], [ 'id' => $case_id ] );
};

$options = get_option( 'mh_fw_settings', [] );
$options['noten_reminder_days']  = 3;
$options['noten_escalate_after'] = 2;
update_option( 'mh_fw_settings', $options );

$backdate( $case2_id, 'notified_at' );
$stats = $reminder->run();
check( 'Genau eine Erinnerung verschickt', 1 === $stats['reminded'], json_encode( $stats ) );
$case2 = $case_repo->get_by_id( $case2_id );
check( 'reminder_count hochgezaehlt', 1 === (int) $case2['form_data']['items'][0]['reminder_count'] );

$stats2 = $reminder->run();
check( 'Kein erneutes Erinnern innerhalb des Intervalls', 0 === $stats2['reminded'], json_encode( $stats2 ) );

$backdate( $case2_id, 'last_reminder_at' );
$GLOBALS['mh_sent_mails'] = [];
$stats3 = $reminder->run();
check( 'Zweite Erinnerung + Eskalation', 1 === $stats3['reminded'] && 1 === $stats3['escalated'], json_encode( $stats3 ) );
$to_list = array_map( fn( $x ) => $x['to'], $GLOBALS['mh_sent_mails'] );
check( 'Eskalation ging an den Klassenlehrer', in_array( 'mh_test_owner@example.test', $to_list, true ) );

$backdate( $case2_id, 'last_reminder_at' );
$stats4 = $reminder->run();
check( 'Eskalation feuert nur einmal', 0 === $stats4['escalated'], json_encode( $stats4 ) );

echo "\n=== 7. Berechtigungen ===\n";
$rc  = new ReflectionClass( $controller );
$can = $rc->getMethod( 'can_edit_item' );
$can->setAccessible( true );
$case   = $case_repo->get_by_id( $case_id );
$item_d = $case['form_data']['items'][0];

wp_set_current_user( (int) $u_full->ID );
check( 'Zugeordnete Lehrkraft darf', true === $can->invoke( $controller, $case, $item_d ) );
wp_set_current_user( (int) $u_owner->ID );
check( 'Klassenlehrer darf (Nachtrag)', true === $can->invoke( $controller, $case, $item_d ) );
wp_set_current_user( (int) $u_fb->ID );
check( 'Fremde Lehrkraft darf NICHT', false === $can->invoke( $controller, $case, $item_d ) );
wp_set_current_user( 0 );
check( 'Nicht angemeldet darf NICHT', false === $can->invoke( $controller, $case, $item_d ) );

echo "\n=== 8. Aufraeumen ===\n";
foreach ( [ $case_id, $case2_id, $submission_id, $case2_sub ] as $id ) {
	$wpdb->delete( $wpdb->prefix . 'mh_form_submissions', [ 'id' => $id ] );
}
foreach ( [ '__TFULL__', '__TFB__', '__TNONE__' ] as $k ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $tbl_acc WHERE kuerzel = %s", $k ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( [ $u_full, $u_fb, $u_owner ] as $u ) { wp_delete_user( (int) $u->ID ); }
echo "Testdaten entfernt.\n";

echo "\n============================\n";
echo 0 === $fails ? "ALLE PRUEFUNGEN BESTANDEN\n" : "$fails PRUEFUNG(EN) FEHLGESCHLAGEN\n";
exit( 0 === $fails ? 0 : 1 );
