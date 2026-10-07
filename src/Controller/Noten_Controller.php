<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Service\Mail_Service;
use Mh\FormWorkflows\Service\Background_Response;
use Mh\FormWorkflows\Service\Reminder_Service;

/**
 * Class Noten_Controller
 *
 * Frontend und POST-Handler der digitalen Noteneinsammlung. Aufbau wie Fall_Controller:
 * Shortcodes auf konfigurierten Seiten, Handler über admin_post_* mit Nonce, Templates
 * bekommen ihre Daten als lokale Variablen.
 *
 * Zugriffsschutz: keine Tokens in URLs. Der Mail-Link führt bei fehlender Anmeldung über
 * den WordPress-Login zurück auf die Zielseite — eine weitergeleitete Mail öffnet damit
 * keine fremden Schülerdaten.
 */
class Noten_Controller {

	public function __construct(
		private Noten_Fall_Repository $case_repo,
		private Teacher_Account_Repository $account_repo,
		private Submission_Repository $submission_repo,
		private Mail_Service $mail,
		private Reminder_Service $reminder
	) {}

	// ---------------------------------------------------------------
	// Ansichten
	// ---------------------------------------------------------------

	/**
	 * Eingabemaske einer Fachlehrkraft. Zeigt bewusst NUR die eigene Zeile —
	 * keine fremden Noten, keine Abmeldegründe.
	 */
	public function render_eingabe(): string {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$case_id = isset( $_GET['mh_noten_id'] ) ? (int) $_GET['mh_noten_id'] : 0;
		$idx     = isset( $_GET['mh_item'] ) ? (int) $_GET['mh_item'] : -1;

		if ( $case_id <= 0 ) {
			return $this->render_liste();
		}

		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case ) {
			return '<p>Diese Ausschulung wurde nicht gefunden.</p>';
		}

		$item = $this->find_item( $case, $idx );
		if ( null === $item ) {
			return '<p>Dieses Fach gehört nicht zu der Ausschulung.</p>';
		}
		if ( ! $this->can_edit_item( $case, $item ) ) {
			return '<p>Diese Noteneingabe ist einer anderen Lehrkraft zugewiesen.</p>';
		}
		// Nach dem Ende der Einsammlung trägt nur noch die Klassenleitung nach. Eine
		// späte Eingabe der Fachlehrkraft käme sonst nicht mehr im PDF an.
		if ( ! $this->is_running( $case ) && ! $this->is_owner( $case ) ) {
			return '<p>Die Noteneinsammlung für diese Ausschulung ist bereits abgeschlossen. '
				. 'Falls deine Note noch fehlt, gib sie bitte direkt an die Klassenleitung weiter.</p>';
		}

		$state       = $this->get_state();
		$form_errors = $state['errors'] ?? [];
		$is_success  = ! empty( $state['success'] );
		$is_owner    = $this->is_owner( $case );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/noten/eingabe.php';

		return ob_get_clean() ?: '';
	}

	/**
	 * "Meine Noteneingaben" — offene und abgeschlossene Ausschulungen, an denen die
	 * angemeldete Lehrkraft mit Noten beteiligt ist.
	 */
	public function render_liste(): string {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$user_id      = get_current_user_id();
		$kuerzel_list = $this->account_repo->get_kuerzel_for_user( $user_id );
		$status       = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$entries      = $this->case_repo->get_cases_for_teacher( $user_id, $kuerzel_list, '' !== $status ? $status : null );
		$reminder     = $this->reminder;

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/noten/liste.php';

		return ob_get_clean() ?: '';
	}

	/**
	 * Klassenlehrer-Sicht: Fortschritt, wer noch fehlt, Nachtragen, Erinnerung auslösen.
	 */
	public function render_fall(): string {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$case_id = isset( $_GET['mh_noten_id'] ) ? (int) $_GET['mh_noten_id'] : 0;

		if ( $case_id <= 0 ) {
			$cases    = $this->case_repo->get_all_cases(
				current_user_can( 'manage_options' ) ? [] : [ 'owner_user_id' => get_current_user_id() ]
			);
			$reminder = $this->reminder;

			ob_start();
			include MH_FW_PLUGIN_DIR . 'templates/noten/fall-liste.php';

			return ob_get_clean() ?: '';
		}

		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case ) {
			return '<p>Diese Ausschulung wurde nicht gefunden.</p>';
		}
		if ( ! $this->is_owner( $case ) ) {
			return '<p>Diese Ausschulung gehört einer anderen Lehrkraft.</p>';
		}

		$state       = $this->get_state();
		$form_errors = $state['errors'] ?? [];
		$notice      = $state['notice'] ?? '';
		$open_count  = $this->case_repo->count_open_items( $case );
		$account     = $this->account_repo;
		$reminder    = $this->reminder;
		$pdf_url     = $this->pdf_download_url( $case );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/noten/fall.php';

		return ob_get_clean() ?: '';
	}

	// ---------------------------------------------------------------
	// Handler
	// ---------------------------------------------------------------

	/**
	 * Speichert eine einzelne Note — durch die Fachlehrkraft oder als Nachtrag
	 * durch den Klassenlehrer.
	 */
	public function handle_save_item(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_noten_save_item' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( 'Nicht angemeldet.' );
		}

		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		$idx     = (int) ( $_POST['item_idx'] ?? -1 );
		$case    = $this->case_repo->get_by_id( $case_id );
		$item    = $this->find_item( $case, $idx );

		if ( null === $case || null === $item || ! $this->can_edit_item( $case, $item ) ) {
			wp_die( 'Kein Zugriff auf diese Noteneingabe.' );
		}
		if ( ! $this->is_running( $case ) && ! $this->is_owner( $case ) ) {
			wp_die( 'Die Noteneinsammlung ist bereits abgeschlossen. Bitte gib die Note direkt an die Klassenleitung weiter.' );
		}

		$grade  = sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) );
		$remark = sanitize_textarea_field( wp_unslash( $_POST['remark'] ?? '' ) );

		$errors = [];
		if ( '' === $grade ) {
			$errors['grade'] = 'Bitte eine Note auswählen.';
		}
		// Dieselbe Regel wie im Hauptformular: NB verlangt eine Begründung.
		if ( 'NB' === $grade && '' === trim( $remark ) ) {
			$errors['remark'] = 'Bei der Note "NB" ist eine Begründung zwingend erforderlich.';
		}

		$return_url = $this->reminder->entry_link( $case_id, $idx );

		if ( ! empty( $errors ) ) {
			$this->set_state( [ 'errors' => $errors, 'success' => false ] );
			wp_redirect( $return_url );
			exit;
		}

		$role = $this->is_owner( $case ) && (int) $item['recipient_user_id'] !== get_current_user_id()
			? 'klassenlehrer'
			: 'fachlehrer';

		// "Fach vorher abgeschlossen" steht in diesem Formular bewusst nicht zur Wahl -
		// es gehört zur selbst eingetragenen Note und wird im Abmeldeformular gesetzt.
		// Der bestehende Wert wird deshalb durchgereicht statt stillschweigend geleert.
		$this->case_repo->set_item_grade( $case_id, $idx, [
			'grade'     => $grade,
			'remark'    => $remark,
			'webuntis'  => isset( $_POST['webuntis'] ),
			'completed' => '1' === ( $item['completed'] ?? '0' ),
		], get_current_user_id(), $role );

		if ( ! $this->is_running( $case ) ) {
			// Korrektur oder Nachtrag der Klassenleitung nach dem Abschluss: direkt in die
			// Abmeldung übernehmen, sonst stünde im PDF weiter der alte Stand.
			$fresh = $this->case_repo->get_by_id( $case_id );
			if ( null !== $fresh ) {
				$this->write_back_to_submission( $fresh );
			}
		} elseif ( ! $this->maybe_complete_case( $case_id ) ) {
			// Zwischenstand an die Klassenleitung - aber nur, wenn jemand anderes die
			// Note eingetragen hat. Die letzte Note löst stattdessen die Abschlussmail aus.
			$this->notify_owner_grade_entered( $case_id, $idx );
		}

		$this->set_state( [ 'errors' => [], 'success' => true ] );
		wp_redirect( $return_url );
		exit;
	}

	/**
	 * Die Klassenleitung beendet die Einsammlung: offene Fächer werden nicht mehr
	 * angefragt, der bisherige Stand wandert in die Abmeldung, das PDF ist druckbar.
	 * Fehlende Noten trägt sie selbst nach (z. B. im Lehrerzimmer erfragt) oder
	 * ergänzt sie im PDF von Hand.
	 */
	public function handle_end_case(): void {
		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		check_admin_referer( 'mh_noten_end_case_' . $case_id );

		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->is_owner( $case ) ) {
			wp_die( 'Kein Zugriff auf diese Noteneinsammlung.' );
		}

		if ( $this->is_running( $case ) ) {
			$this->case_repo->end_case( $case_id, get_current_user_id() );
			$fresh = $this->case_repo->get_by_id( $case_id );
			if ( null !== $fresh ) {
				$this->write_back_to_submission( $fresh );
			}
			$open = $this->case_repo->count_open_items( $case );
			$this->set_state( [ 'notice' => 0 === $open
				? 'Noteneinsammlung beendet. Das PDF ist bereit.'
				: 'Noteneinsammlung beendet. ' . $open . ' Note(n) fehlen noch – bitte unten nachtragen oder im PDF von Hand ergänzen.' ] );
		}

		wp_redirect( $this->reminder->case_link( $case_id ) );
		exit;
	}

	/**
	 * Klassenleitung: PDF ist gedruckt und weitergegeben - aus dem Dashboard nehmen.
	 */
	public function handle_owner_done(): void {
		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		check_admin_referer( 'mh_noten_owner_done_' . $case_id );

		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->is_owner( $case ) ) {
			wp_die( 'Kein Zugriff auf diese Noteneinsammlung.' );
		}
		if ( ! $this->is_running( $case ) ) {
			$this->case_repo->mark_owner_done( $case_id );
		}

		$back = wp_get_referer() ?: $this->reminder->case_link( $case_id );
		wp_redirect( $back );
		exit;
	}

	/**
	 * Link zum PDF der zugehörigen Abmeldung. Nur für die Person, die die Abmeldung
	 * angelegt hat - so prüft es auch der Download-Handler. Leer, wenn es keine gibt.
	 */
	public function pdf_download_url( array $case ): string {
		$submission_id = (int) ( $case['form_data']['submission_id'] ?? 0 );
		if ( $submission_id <= 0 ) {
			return '';
		}
		$entry = $this->submission_repo->get_by_id( $submission_id );
		if ( null === $entry || (int) $entry['user_id'] !== get_current_user_id() ) {
			return '';
		}

		return add_query_arg( [
			'mh_action' => 'download',
			'id'        => $submission_id,
			'_wpnonce'  => wp_create_nonce( 'mh_dashboard_action_' . $submission_id ),
		], home_url( '/' ) );
	}

	private function notify_owner_grade_entered( int $case_id, int $idx ): void {
		$case = $this->case_repo->get_by_id( $case_id );
		$item = $this->find_item( $case, $idx );
		if ( null === $case || null === $item ) {
			return;
		}

		$owner_id = (int) ( $case['form_data']['owner_user_id'] ?? $case['user_id'] ?? 0 );
		// Wer selbst einträgt, braucht darüber keine Mail.
		if ( $owner_id <= 0 || $owner_id === get_current_user_id() ) {
			return;
		}
		$to = $this->account_repo->resolve_email_for_user( $owner_id );
		if ( '' === $to ) {
			return;
		}

		$current = wp_get_current_user();
		$name    = $this->account_repo->get_display_name( (string) ( $item['teacher_kuerzel'] ?? '' ) ) ?: $current->display_name;

		$this->mail->send_grade_entered(
			$to,
			$case,
			$item,
			$name,
			$this->case_repo->count_open_items( $case ),
			$this->reminder->case_link( $case_id )
		);
	}

	private function is_running( array $case ): bool {
		return 'offen' === ( $case['status'] ?? '' );
	}

	/**
	 * Erinnerung sofort senden (Klassenlehrer, ohne auf den Cron zu warten).
	 */
	public function handle_remind_now(): void {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'mh_noten_remind' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		$idx     = (int) ( $_GET['item_idx'] ?? -1 );
		$case    = $this->case_repo->get_by_id( $case_id );
		$item    = $this->find_item( $case, $idx );

		if ( null === $case || null === $item || ! $this->is_owner( $case ) ) {
			wp_die( 'Kein Zugriff auf diese Ausschulung.' );
		}

		$recipient = $this->account_repo->resolve_recipient( (string) $item['teacher_kuerzel'] );

		// Aus der Admin-Übersicht aufgerufen: dorthin zurück statt auf die Frontend-Seite.
		$from_admin = isset( $_GET['from'] ) && 'admin' === $_GET['from'] && current_user_can( 'manage_options' );
		$back_url   = $from_admin ? $this->admin_url() : $this->reminder->case_link( $case_id );

		if ( null === $recipient ) {
			$this->set_state( [ 'notice' => 'Für ' . $item['teacher_kuerzel'] . ' ist keine Adresse hinterlegt — es wurde nichts gesendet.' ] );
			wp_redirect( $back_url );
			exit;
		}

		// Wurde noch nie erfolgreich eingeladen, ist die Mail eine Einladung, keine Erinnerung.
		$never_notified = empty( $item['notified_at'] );
		$kind           = $never_notified ? 'Einladung' : 'Erinnerung';

		// Versand erst nach der Weiterleitung: ein langsamer Mailserver hielt sonst den
		// Tab minutenlang im Ladezustand, obwohl die Mail längst raus war. Das Ergebnis
		// steht danach am Fach (zuletzt gesendet bzw. Mailfehler).
		$this->set_state( [ 'notice' => $kind . ' an ' . $recipient['email'] . ' wird gesendet. Ob sie rausging, steht nach dem Neuladen am Fach.' ] );
		Background_Response::redirect_and_continue( $back_url );

		$link = $this->reminder->entry_link( $case_id, $idx );
		$sent = $never_notified
			? $this->mail->send_invitation( $recipient['email'], $recipient['name'], $case, $item, $link )
			: $this->mail->send_reminder( $recipient['email'], $recipient['name'], $case, $item, $link, (int) ( $item['reminder_count'] ?? 0 ) + 1 );

		if ( $sent ) {
			$this->case_repo->mark_notified( $case_id, $idx, ! $never_notified, $recipient['email'] );
		} else {
			$this->case_repo->mark_mail_failed( $case_id, $idx, $this->mail->get_last_error() );
		}
		exit;
	}

	// ---------------------------------------------------------------
	// Admin-Übersicht
	// ---------------------------------------------------------------

	public const ADMIN_SLUG = 'mh-form-noten-admin';

	private function admin_url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::ADMIN_SLUG ) );
	}

	/**
	 * Backend: alle Noteneinsammlungen mit Fortschritt und erkannten Problemen.
	 * Liest nur - Eingriffe laufen über die bestehenden Handler bzw. handle_admin_complete().
	 */
	public function render_admin_overview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sie haben keine Berechtigung für diese Seite.' );
		}

		$f_status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'offen';
		$f_status   = in_array( $f_status, [ 'offen', 'abgeschlossen', 'alle' ], true ) ? $f_status : 'offen';
		$f_problems = ! empty( $_GET['probleme'] );

		$all_cases = $this->case_repo->get_all_cases();

		$rows    = [];
		$summary = [ 'open_cases' => 0, 'open_items' => 0, 'problem_cases' => 0, 'closed_cases' => 0 ];
		foreach ( $all_cases as $case ) {
			$diag    = $this->diagnose_case( $case );
			$is_open = 'offen' === ( $case['status'] ?? '' );

			if ( $is_open ) {
				$summary['open_cases']++;
				$summary['open_items'] += $diag['open_count'];
			} else {
				$summary['closed_cases']++;
			}
			if ( $diag['has_error'] ) {
				$summary['problem_cases']++;
			}

			if ( 'alle' !== $f_status && ( $is_open ? 'offen' : 'abgeschlossen' ) !== $f_status ) {
				continue;
			}
			if ( $f_problems && empty( $diag['problems'] ) ) {
				continue;
			}
			$rows[] = [ 'case' => $case, 'diag' => $diag ];
		}

		// Fälle mit Fehlern zuerst, danach die ältesten - die brauchen am ehesten Aufmerksamkeit.
		usort( $rows, static function ( array $a, array $b ): int {
			return [ $b['diag']['has_error'], $a['case']['created_at'] ] <=> [ $a['diag']['has_error'], $b['case']['created_at'] ];
		} );

		$system_checks = $this->system_checks( $summary['open_cases'] );
		$state         = $this->get_state();
		$notice        = $state['notice'] ?? '';
		$reminder      = $this->reminder;
		$admin_url     = $this->admin_url();

		include MH_FW_PLUGIN_DIR . 'templates/noten/admin-uebersicht.php';
	}

	/**
	 * Abschluss nachholen: für Fälle, in denen alle Noten vorliegen, der Fall aber
	 * trotzdem offen blieb (z. B. weil der Request beim Speichern der letzten Note abbrach).
	 */
	public function handle_admin_complete(): void {
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		check_admin_referer( 'mh_noten_admin_complete_' . $case_id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case ) {
			$notice = 'Fall nicht gefunden.';
		} elseif ( $this->case_repo->count_open_items( $case ) > 0 ) {
			$notice = 'Der Fall hat noch offene Noten und kann nicht abgeschlossen werden.';
		} else {
			$notice = $this->maybe_complete_case( $case_id )
				? 'Fall abgeschlossen, Noten in die Abmeldung übernommen, Klassenleitung benachrichtigt.'
				: 'Der Fall konnte nicht abgeschlossen werden (bereits abgeschlossen?).';
		}

		$this->set_state( [ 'notice' => $notice ] );
		wp_redirect( $this->admin_url() );
		exit;
	}

	/**
	 * Prüft einen Fall auf alles, was ihn unbemerkt liegen lassen könnte.
	 *
	 * @return array{open_count:int, total_collect:int, problems:array<int, array{level:string, text:string}>,
	 *               has_error:bool, items:array<int, array{problems:array}>, submission_exists:bool, stuck:bool}
	 */
	private function diagnose_case( array $case ): array {
		$is_open   = 'offen' === ( $case['status'] ?? '' );
		$items     = $case['form_data']['items'] ?? [];
		$now       = current_time( 'timestamp' );
		$interval  = $this->reminder->get_interval_days();
		$escalate  = $this->reminder->get_escalate_after();
		// Spätestens nach allen Erinnerungen plus einem Intervall sollte eine Note da sein.
		$overdue_days = $interval * ( $escalate + 1 );

		$problems      = [];
		$item_diag     = [];
		$open_count    = 0;
		$total_collect = 0;

		foreach ( $items as $item ) {
			$idx     = (int) ( $item['idx'] ?? 0 );
			$ip      = [];
			$is_done = 'erledigt' === ( $item['status'] ?? 'offen' );
			if ( '1' === ( $item['collect'] ?? '0' ) ) {
				$total_collect++;
			}
			if ( ! $is_done ) {
				$open_count++;
			}

			if ( $is_open && ! $is_done ) {
				$kuerzel = (string) ( $item['teacher_kuerzel'] ?? '' );

				if ( '' !== (string) ( $item['mail_error'] ?? '' ) ) {
					$ip[] = [ 'level' => 'error', 'text' => 'Mail fehlgeschlagen: ' . $item['mail_error'] ];
				} elseif ( empty( $item['notified_at'] ) ) {
					$ip[] = [ 'level' => 'error', 'text' => 'Nie benachrichtigt' ];
				}
				if ( null === $this->account_repo->resolve_recipient( $kuerzel ) ) {
					$ip[] = [ 'level' => 'error', 'text' => 'Keine Mailadresse für ' . ( '' !== $kuerzel ? $kuerzel : '(kein Kürzel)' ) ];
				} elseif ( ! empty( $item['is_fallback'] ) ) {
					$ip[] = [ 'level' => 'warn', 'text' => 'Nur Konto-Adresse (Rückfall) – wird evtl. nicht gelesen' ];
				}
				if ( ! empty( $item['escalated_at'] ) ) {
					$ip[] = [ 'level' => 'warn', 'text' => 'Eskaliert am ' . date_i18n( 'd.m.Y', strtotime( (string) $item['escalated_at'] ) ) ];
				}
				$started = strtotime( (string) ( $case['form_data']['started_at'] ?? $case['created_at'] ?? '' ) );
				if ( false !== $started && ( $now - $started ) > $overdue_days * DAY_IN_SECONDS ) {
					$ip[] = [ 'level' => 'warn', 'text' => 'Seit ' . (int) floor( ( $now - $started ) / DAY_IN_SECONDS ) . ' Tagen offen' ];
				}
			}

			$item_diag[ $idx ] = [ 'problems' => $ip ];
			foreach ( $ip as $p ) {
				$problems[] = [ 'level' => $p['level'], 'text' => ( $item['subject'] ?? '' ) . ' (' . ( $item['teacher_kuerzel'] ?? '' ) . '): ' . $p['text'] ];
			}
		}

		// Alle Noten da, Fall trotzdem offen: der Abschluss (Zurückschreiben + Mail) fehlt.
		$stuck = $is_open && 0 === $open_count && ! empty( $items );
		if ( $stuck ) {
			$problems[] = [ 'level' => 'error', 'text' => 'Alle Noten liegen vor, der Fall wurde aber nicht abgeschlossen.' ];
		}

		// Abschlussmail an die Klassenleitung gescheitert: sie weiß dann nicht, dass das PDF bereit ist.
		if ( ! $is_open && '' !== (string) ( $case['form_data']['completion_mail_error'] ?? '' ) ) {
			$problems[] = [ 'level' => 'warn', 'text' => 'Abschlussmail an die Klassenleitung fehlgeschlagen: ' . $case['form_data']['completion_mail_error'] ];
		}

		// Ohne Abmeldung können die Noten beim Abschluss nirgendwohin zurückgeschrieben werden.
		$submission_id     = (int) ( $case['form_data']['submission_id'] ?? 0 );
		$submission_exists = $submission_id > 0 && null !== $this->submission_repo->get_by_id( $submission_id );
		if ( $is_open && ! $submission_exists ) {
			$problems[] = [ 'level' => 'error', 'text' => 'Die zugehörige Abmeldung existiert nicht mehr – eingesammelte Noten gehen beim Abschluss verloren.' ];
		}

		$has_error = false;
		foreach ( $problems as $p ) {
			if ( 'error' === $p['level'] ) {
				$has_error = true;
				break;
			}
		}

		return [
			'open_count'        => $open_count,
			'total_collect'     => $total_collect,
			'problems'          => $problems,
			'has_error'         => $has_error,
			'items'             => $item_diag,
			'submission_exists' => $submission_exists,
			'stuck'             => $stuck,
		];
	}

	/**
	 * Voraussetzungen des Verfahrens, die nicht an einem einzelnen Fall hängen.
	 *
	 * @return array<int, array{level:string, text:string}>
	 */
	private function system_checks( int $open_cases ): array {
		$checks  = [];
		$options = get_option( 'mh_fw_settings', [] );

		if ( ! \Mh\FormWorkflows\Service\Noten_Feature::is_enabled() ) {
			$checks[] = [ 'level' => 'info', 'text' => 'Die digitale Noteneinsammlung ist abgeschaltet: neue Einsammlungen sind gesperrt, laufende werden zu Ende geführt.' ];
		}

		$pages = [
			'page_id_mh_noten_eingabe' => 'Noteneingabe ([mh_noten_eingabe]) – Ziel der Einladungs- und Erinnerungsmails',
			'page_id_mh_noten_fall'    => 'Klassenleitungs-Ansicht ([mh_noten_fall]) – Ziel der Abschluss- und Eskalationsmails',
		];
		foreach ( $pages as $key => $label ) {
			$page_id = (int) ( $options[ $key ] ?? 0 );
			if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
				$checks[] = [ 'level' => 'error', 'text' => 'Seite nicht zugeordnet oder nicht veröffentlicht: ' . $label . '. Links in den Mails führen sonst auf die Startseite.' ];
			}
		}

		$next = wp_next_scheduled( Reminder_Service::CRON_HOOK );
		if ( false === $next ) {
			$checks[] = [ 'level' => 'error', 'text' => 'Der Erinnerungs-Cron ist nicht geplant. Er wird beim nächsten Seitenaufruf neu angelegt – bleibt diese Meldung, bitte melden.' ];
		} else {
			$checks[] = [ 'level' => 'ok', 'text' => 'Nächster Erinnerungslauf: ' . wp_date( 'd.m.Y H:i', $next ) . ' Uhr' ];
		}

		$last = $this->reminder->get_last_run();
		if ( null === $last ) {
			$checks[] = [ 'level' => $open_cases > 0 ? 'warn' : 'info', 'text' => 'Seit diesem Update ist noch kein Erinnerungslauf protokolliert.' ];
		} else {
			$s    = $last['stats'] ?? [];
			$text = sprintf(
				'Letzter Erinnerungslauf: %s Uhr – %d erinnert, %d nachträglich eingeladen, %d eskaliert, %d fehlgeschlagen, %d übersprungen.',
				date_i18n( 'd.m.Y H:i', strtotime( (string) $last['at'] ) ),
				(int) ( $s['reminded'] ?? 0 ),
				(int) ( $s['invited'] ?? 0 ),
				(int) ( $s['escalated'] ?? 0 ),
				(int) ( $s['failed'] ?? 0 ),
				(int) ( $s['skipped'] ?? 0 )
			);
			$age   = current_time( 'timestamp' ) - (int) strtotime( (string) $last['at'] );
			$level = ( (int) ( $s['failed'] ?? 0 ) > 0 ) ? 'warn' : 'ok';
			if ( $open_cases > 0 && $age > 2 * DAY_IN_SECONDS ) {
				$level = 'warn';
				$text .= ' Das ist über zwei Tage her – WP-Cron läuft nur bei Seitenaufrufen; ein Server-Cron auf wp-cron.php schafft Abhilfe.';
			}
			$checks[] = [ 'level' => $level, 'text' => $text ];
		}

		return $checks;
	}

	// ---------------------------------------------------------------
	// Prozesslogik
	// ---------------------------------------------------------------

	/**
	 * Schließt den Fall, sobald keine Note mehr offen ist: Noten zurück in die
	 * Einsendung schreiben (damit der bestehende PDF-Download sie enthält) und den
	 * Klassenlehrer informieren.
	 */
	public function maybe_complete_case( int $case_id ): bool {
		$case = $this->case_repo->get_by_id( $case_id );
		if ( null === $case || 'offen' !== ( $case['status'] ?? '' ) ) {
			return false;
		}
		if ( $this->case_repo->count_open_items( $case ) > 0 ) {
			return false;
		}

		$this->write_back_to_submission( $case );
		$this->case_repo->close_case( $case_id );

		$owner_id = (int) ( $case['form_data']['owner_user_id'] ?? $case['user_id'] ?? 0 );
		$to       = $this->account_repo->resolve_email_for_user( $owner_id );
		if ( '' !== $to ) {
			$sent = $this->mail->send_completion( $to, $case, $this->reminder->case_link( $case_id ) );
			// Ergebnis am Fall festhalten: bleibt die Abschlussmail aus, zeigt die
			// Admin-Übersicht jetzt, warum.
			$this->case_repo->set_meta( $case_id, $sent
				? [ 'completion_mail_at' => current_time( 'mysql' ), 'completion_mail_error' => '' ]
				: [ 'completion_mail_error' => $this->mail->get_last_error() ] );
		} else {
			$this->case_repo->set_meta( $case_id, [ 'completion_mail_error' => 'Klassenleitung hat keine E-Mail-Adresse im Benutzerkonto.' ] );
		}

		return true;
	}

	/**
	 * Überträgt die eingesammelten Noten in form_data['subjects'] der Einsendung.
	 * pdf-abmeldung.php und pdf-protocol.php rendern direkt daraus — deshalb braucht
	 * es hier keinen eigenen PDF-Pfad.
	 */
	private function write_back_to_submission( array $case ): bool {
		$submission_id = (int) ( $case['form_data']['submission_id'] ?? 0 );
		if ( $submission_id <= 0 ) {
			return false;
		}

		$entry = $this->submission_repo->get_by_id( $submission_id );
		if ( null === $entry ) {
			return false;
		}

		$form_data = is_string( $entry['form_data'] ) ? json_decode( $entry['form_data'], true ) : $entry['form_data'];
		if ( ! is_array( $form_data ) ) {
			return false;
		}

		$form_data['subjects'] = $this->case_repo->build_subjects_from_case( $case );

		// Eine NB-Begründung gehört ins Konferenzprotokoll — sonst ginge sie beim
		// Zurückschreiben verloren, obwohl das Hauptformular sie dort erwartet.
		$nb_remarks = [];
		foreach ( $case['form_data']['items'] ?? [] as $item ) {
			if ( 'NB' === ( $item['grade'] ?? '' ) && '' !== trim( (string) ( $item['remark'] ?? '' ) ) ) {
				$nb_remarks[] = $item['subject'] . ': ' . $item['remark'];
			}
		}
		if ( ! empty( $nb_remarks ) ) {
			$existing              = trim( (string) ( $form_data['prot_remarks'] ?? '' ) );
			$appended              = implode( "\n", $nb_remarks );
			$form_data['prot_remarks'] = '' !== $existing ? $existing . "\n" . $appended : $appended;
		}

		return $this->submission_repo->update(
			$submission_id,
			[
				'form_type' => $entry['form_type'],
				'status'    => $entry['status'],
				'user_id'   => (int) $entry['user_id'],
				'form_data' => $form_data,
			],
			(int) $entry['user_id']
		);
	}

	// ---------------------------------------------------------------
	// Helfer
	// ---------------------------------------------------------------

	private function find_item( ?array $case, int $idx ): ?array {
		if ( null === $case || $idx < 0 ) {
			return null;
		}
		foreach ( $case['form_data']['items'] ?? [] as $item ) {
			if ( (int) $item['idx'] === $idx ) {
				return $item;
			}
		}

		return null;
	}

	private function is_owner( array $case ): bool {
		return current_user_can( 'manage_options' )
			|| (int) ( $case['form_data']['owner_user_id'] ?? $case['user_id'] ?? 0 ) === get_current_user_id();
	}

	/**
	 * Bearbeiten darf: die zugeordnete Lehrkraft, der Klassenlehrer (Nachtrag) und Admins.
	 * Zusätzlich greift der Kürzel-Pfad, falls die Benutzer-Zuordnung erst nach dem
	 * Start des Falls gepflegt wurde.
	 */
	private function can_edit_item( array $case, array $item ): bool {
		if ( $this->is_owner( $case ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( (int) ( $item['recipient_user_id'] ?? 0 ) === $user_id && $user_id > 0 ) {
			return true;
		}

		return in_array(
			(string) ( $item['teacher_kuerzel'] ?? '' ),
			$this->account_repo->get_kuerzel_for_user( $user_id ),
			true
		);
	}

	private function set_state( array $state ): void {
		set_transient( 'mh_fw_noten_state_' . get_current_user_id(), $state, 60 );
	}

	private function get_state(): array {
		$key   = 'mh_fw_noten_state_' . get_current_user_id();
		$state = get_transient( $key );
		if ( false === $state ) {
			return [];
		}
		delete_transient( $key );

		return $state;
	}
}
