<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Service\Mail_Service;
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

		$this->case_repo->set_item_grade( $case_id, $idx, [
			'grade'     => $grade,
			'remark'    => $remark,
			'webuntis'  => isset( $_POST['webuntis'] ),
			'completed' => isset( $_POST['completed'] ),
		], get_current_user_id(), $role );

		$this->maybe_complete_case( $case_id );

		$this->set_state( [ 'errors' => [], 'success' => true ] );
		wp_redirect( $return_url );
		exit;
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

		if ( null === $recipient ) {
			$this->set_state( [ 'notice' => 'Für ' . $item['teacher_kuerzel'] . ' ist keine Adresse hinterlegt — es wurde nichts gesendet.' ] );
		} else {
			$this->mail->send_reminder(
				$recipient['email'],
				$recipient['name'],
				$case,
				$item,
				$this->reminder->entry_link( $case_id, $idx ),
				(int) ( $item['reminder_count'] ?? 0 ) + 1
			);
			$this->case_repo->mark_notified( $case_id, $idx, true );
			$this->set_state( [ 'notice' => 'Erinnerung an ' . $recipient['email'] . ' gesendet.' ] );
		}

		wp_redirect( $this->reminder->case_link( $case_id ) );
		exit;
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
		$owner    = $owner_id > 0 ? get_userdata( $owner_id ) : false;
		if ( $owner && '' !== (string) $owner->user_email ) {
			$this->mail->send_completion( (string) $owner->user_email, $case, $this->reminder->case_link( $case_id ) );
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
