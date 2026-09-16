<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
use Mh\FormWorkflows\Repository\Subject_Repository;
use Mh\FormWorkflows\Repository\Track_Subject_Repository;
use Mh\FormWorkflows\Repository\Student_Course_Repository;
use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Service\Mail_Service;
use Mh\FormWorkflows\Service\Reminder_Service;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Model\Form\Form_Interface;
use Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form;
use Mh\FormWorkflows\Model\Form\Service_Leave_Form;

class Form_Controller {

	/**
	 * Konstruktor mit allen Abhängigkeiten (muss zur Bootstrap passen!)
	 */
	public function __construct(
		private Submission_Repository $repository,
		private Class_Repository $class_repo,
		private Teacher_Repository $teacher_repo,
		private Student_Repository $student_repo,
                private Subject_Repository $subject_repo,
		private Track_Subject_Repository $track_subject_repo,
		private Student_Course_Repository $student_course_repo,
		private Noten_Fall_Repository $noten_repo,
		private Teacher_Account_Repository $account_repo,
		private Mail_Service $mail,
		private Reminder_Service $reminder,
		private Pdf_Generator $pdf_generator
	) {}

	/**
	 * AJAX-Endpunkt: Liefert die Fächer-Vorbelegung für einen Schüler.
	 *
	 * Vereinigt zwei Schild-Quellen, die ab Schuljahresbeginn gepflegt sind:
	 * - die Stundentafel des Bildungsgangs (Regelfächer, für alle Schüler gleich)
	 * - die klassenübergreifenden Kursbelegungen des Schülers (nur dort steht eine Lehrkraft)
	 *
	 * Bewusst NICHT genutzt wird die schülerbezogene Fachbelegung aus Schild: die entsteht
	 * erst kurz vor der Notensammlung und ist bei einer Abmeldung meist noch leer.
	 */
	public function ajax_get_subject_rows(): void {
		if ( ! check_ajax_referer( 'mh_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Sicherheits-Check fehlgeschlagen.' );
		}

		$track_key = isset( $_POST['track_key'] ) ? sanitize_text_field( wp_unslash( $_POST['track_key'] ) ) : '';
		$schild_id = isset( $_POST['schild_id'] ) ? sanitize_text_field( wp_unslash( $_POST['schild_id'] ) ) : '';

		$rows = [];
		$seen = [];

		// 1. Regelfächer aus der Stundentafel des Bildungsgangs.
		$track_subjects = $this->track_subject_repo->get_subjects_for_track( $track_key );
		$track_options  = [];

		foreach ( $track_subjects as $s ) {
			$short   = (string) $s['short_name'];
			$display = (string) ( $s['display_name'] ?? '' );
			$label   = '' !== $display ? $short . ' - ' . $display : $short;

			$track_options[] = [ 'value' => $short, 'label' => $label ];

			if ( isset( $seen[ $short ] ) ) {
				continue;
			}
			$seen[ $short ] = true;

			$rows[] = [
				'value'     => $short,
				'label'     => $label,
				'teacher'   => '',
				'is_course' => false,
			];
		}

		// 2. Klassenübergreifende Kurse. Angezeigt wird die Kursbezeichnung — das Trägerfach
		//    (z.B. "Kurs_11_12") ist im Konferenzprotokoll nicht aussagekräftig.
		foreach ( $this->student_course_repo->get_courses_for_student( $schild_id ) as $c ) {
			$course_name = (string) $c['course_name'];
			if ( '' === $course_name || isset( $seen[ $course_name ] ) ) {
				continue;
			}
			$seen[ $course_name ] = true;

			$rows[] = [
				'value'     => $course_name,
				'label'     => $course_name,
				'teacher'   => (string) ( $c['teacher_short'] ?? '' ),
				'is_course' => true,
			];
		}

		// Der Rest der Schild-Fächerliste bleibt als Notausgang erreichbar (Fachwechsler,
		// Wiederholer), steht im Dropdown aber unterhalb der Bildungsgang-Fächer.
		$other_options = [];
		foreach ( $this->subject_repo->get_all_subjects() as $sub ) {
			$short = (string) $sub['short_name'];
			if ( isset( $seen[ $short ] ) ) {
				continue;
			}
			$other_options[] = [
				'value' => $short,
				'label' => $short . ' - ' . $sub['display_name'],
			];
		}

		wp_send_json_success( [
			'rows'          => $rows,
			'track_options' => $track_options,
			'other_options' => $other_options,
		] );
	}
	
	/**
	 * AJAX-Endpunkt: Holt Schüler einer Klasse
	 */
	public function ajax_get_students(): void {
		// 1. Sicherheit: Nonce prüfen
		if ( ! check_ajax_referer( 'mh_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Sicherheits-Check fehlgeschlagen.' );
		}

		// 2. Daten holen
		$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

		if ( $class_id <= 0 ) {
			wp_send_json_error( 'Ungültige Klassen-ID.' );
		}

		// 3. Repository abfragen
		$students = $this->student_repo->get_students_by_class( $class_id );

		if ( ! empty( $students ) ) {
			wp_send_json_success( $students );
		} else {
			wp_send_json_error( 'Keine Schüler für diese Klasse gefunden.' );
		}
	}

	/**
	 * Factory: Erzeugt das passende Model
	 */
	private function get_form_instance( string $type ): Form_Interface {
		return match( $type ) {
			'service_leave_v1'     => new Service_Leave_Form(),
			'abmeldung_student_v1' => new Abmeldung_Student_Form(),
			default                => new Abmeldung_Student_Form(),
		};
	}

	/**
	 * Holt die URL für einen Formular-Typ aus den Admin-Einstellungen.
	 */
	private function get_url_for_form_type(string $type): string {
		$options = get_option( 'mh_fw_settings', [] );
		$key = 'page_id_' . $type;
		$page_id = (int)($options[$key] ?? 0);

		if ($page_id > 0) {
			return get_permalink($page_id) ?: '';
		}
		return ''; 
	}

	/**
	 * Rendert das Formular (Frontend)
	 */
	public function render_form( array $attributes = [] ): string {
		$form_type = $attributes['type'] ?? 'abmeldung_student_v1'; 

		// State laden (Fehler/Inputs nach Reload)
		$transient_key = 'mh_fw_state_' . get_current_user_id();
		$state = get_transient( $transient_key );
		
		$form_data   = [];
		$form_errors = [];
		$is_success  = false;

		if ( false !== $state ) {
			$form_data   = $state['data'] ?? [];
			$form_errors = $state['errors'] ?? [];
			$is_success  = $state['success'] ?? false;
			if(isset($form_data['form_type']) && $form_data['form_type'] !== $form_type) {
				$form_data = []; $form_errors = []; $is_success = false;
			} else {
				delete_transient( $transient_key );
			}
		} else if ( isset($_GET['mh_edit_id']) ) {
			$edit_id = (int)$_GET['mh_edit_id'];
			$entry = $this->repository->get_by_id($edit_id);
			if ($entry && (int)$entry['user_id'] === get_current_user_id()) {
				$form_data = $entry['form_data'];
				$form_data['id'] = $entry['id'];
				$form_data['is_reloaded'] = true; 
			}
		}

		// Stammdaten für Dropdowns laden
		$classes_list = $this->class_repo->get_real_classes();
		$teachers_list = $this->teacher_repo->get_all_teachers();
                $subjects_list = $this->subject_repo->get_all_subjects();

		ob_start();
		if ( 'service_leave_v1' === $form_type ) {
			include MH_FW_PLUGIN_DIR . 'templates/form-service-leave.php';
		} else {
			include MH_FW_PLUGIN_DIR . 'templates/form-abmeldung.php';
		}
		return ob_get_clean() ?: '';
	}

	/**
	 * Dashboard für User
	 */
	public function render_dashboard(): string {
		if ( ! is_user_logged_in() ) return '<p>Bitte anmelden.</p>';

		$user_id = get_current_user_id();
		$submissions = $this->repository->get_submissions_by_user( $user_id );
		
		$urls = [
			'service_leave_v1'     => $this->get_url_for_form_type('service_leave_v1'),
			'abmeldung_student_v1' => $this->get_url_for_form_type('abmeldung_student_v1'),
		];

		$grouped = [];
		foreach ( $submissions as $sub ) {
			$ts = strtotime( $sub['created_at'] );
			$year = (int)date( 'Y', $ts );
			$month = (int)date( 'n', $ts );
			$school_year = ($month < 8) ? ($year - 1) . '/' . substr((string)$year, -2) : $year . '/' . substr((string)($year + 1), -2);
			$sub['data'] = is_string($sub['form_data']) ? json_decode($sub['form_data'], true) : $sub['form_data'];
			$grouped[ $school_year ][] = $sub;
		}

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/dashboard-user.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Dashboard für Admin
	 */
	public function render_admin_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$filters = [
			'start_date' => sanitize_text_field( $_GET['start_date'] ?? '' ),
			'end_date'   => sanitize_text_field( $_GET['end_date'] ?? '' ),
			'user_id'    => sanitize_text_field( $_GET['user_id'] ?? '' ),
			'form_type'  => sanitize_text_field( $_GET['form_type'] ?? '' ),
		];

		$submissions = $this->repository->get_filtered_submissions( $filters );
		$submitters  = $this->repository->get_distinct_submitters();
		
		foreach ( $submissions as &$sub ) {
			$sub['data'] = is_string($sub['form_data']) ? json_decode($sub['form_data'], true) : $sub['form_data'];
		}

		include MH_FW_PLUGIN_DIR . 'templates/dashboard-admin.php';
	}

	/**
	 * Prüft, ob eine digitale Noteneinsammlung überhaupt starten kann.
	 *
	 * Jede belegte Fächerzeile braucht eine Lehrkraft, und zu jedem Kürzel muss sich
	 * eine Zustelladresse auflösen lassen. Fehlt eines von beidem, gibt es niemanden
	 * zu benachrichtigen — der Prozess bliebe unbemerkt liegen.
	 *
	 * @return array<string,string> Fehler für die Rückgabe ins Formular.
	 */
	private function check_collect_preconditions( array $valid_data ): array {
		$subjects = $valid_data['subjects'] ?? [];

		if ( empty( $subjects ) ) {
			return [ 'subjects' => 'Für die Noteneinsammlung muss mindestens ein Fach eingetragen sein.' ];
		}

		$missing_teacher = [];
		$missing_address = [];

		foreach ( $subjects as $s ) {
			$name    = trim( (string) ( $s['name'] ?? '' ) );
			$kuerzel = trim( (string) ( $s['teacher'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			if ( '' === $kuerzel ) {
				$missing_teacher[] = $name;
				continue;
			}
			if ( null === $this->account_repo->resolve_recipient( $kuerzel ) ) {
				$missing_address[] = $name . ' (' . $kuerzel . ')';
			}
		}

		$errors = [];
		if ( ! empty( $missing_teacher ) ) {
			$errors['subjects_teacher'] = 'Für die Noteneinsammlung fehlt die Lehrkraft in folgenden Fächern: '
				. implode( ', ', $missing_teacher ) . '.';
		}
		if ( ! empty( $missing_address ) ) {
			$errors['subjects_address'] = 'Für folgende Lehrkräfte ist keine E-Mail-Adresse hinterlegt: '
				. implode( ', ', $missing_address ) . '. Bitte im WebUntis Analyser unter "Lehrer-Zuordnung" ergänzen.';
		}

		return $errors;
	}

	/**
	 * Legt den Fall an, verschickt die Einladungen und leitet auf die Fall-Ansicht.
	 */
	private function start_grade_collection( int $submission_id, array $valid_data, int $user_id ): void {
		// Doppelstart verhindern (z. B. durch erneutes Absenden desselben Formulars).
		$existing = $this->noten_repo->find_open_case_by_submission( $submission_id );
		if ( null !== $existing ) {
			wp_redirect( $this->reminder->case_link( (int) $existing['id'] ) );
			exit;
		}

		$items = [];
		foreach ( $valid_data['subjects'] ?? [] as $s ) {
			$name    = trim( (string) ( $s['name'] ?? '' ) );
			$kuerzel = trim( (string) ( $s['teacher'] ?? '' ) );
			if ( '' === $name || '' === $kuerzel ) {
				continue;
			}
			$recipient = $this->account_repo->resolve_recipient( $kuerzel );
			if ( null === $recipient ) {
				continue; // von check_collect_preconditions() bereits ausgeschlossen
			}
			$items[] = [
				'subject'           => $name,
				'teacher_kuerzel'   => $kuerzel,
				'recipient_user_id' => $recipient['user_id'],
				'recipient_email'   => $recipient['email'],
				'is_fallback'       => $recipient['is_fallback'],
			];
		}

		$case_id = $this->noten_repo->create_case( [
			'submission_id' => $submission_id,
			'student_wu_id' => (int) ( $valid_data['student_wu_id'] ?? 0 ),
			'lastname'      => (string) ( $valid_data['lastname'] ?? '' ),
			'firstname'     => (string) ( $valid_data['firstname'] ?? '' ),
			'class_wu_id'   => (int) ( $valid_data['class_wu_id'] ?? 0 ),
			'class_name'    => (string) ( $valid_data['class_name'] ?? '' ),
			'owner_user_id' => $user_id,
		], $items, $user_id );

		if ( 0 === $case_id ) {
			wp_die( 'Die Noteneinsammlung konnte nicht angelegt werden (DB-Fehler).' );
		}

		$case = $this->noten_repo->get_by_id( $case_id );
		foreach ( $case['form_data']['items'] as $item ) {
			$idx = (int) $item['idx'];
			$this->mail->send_invitation(
				(string) $item['recipient_email'],
				$this->account_repo->get_display_name( (string) $item['teacher_kuerzel'] ),
				$case,
				$item,
				$this->reminder->entry_link( $case_id, $idx )
			);
			$this->noten_repo->mark_notified( $case_id, $idx, false );
		}

		wp_redirect( $this->reminder->case_link( $case_id ) );
		exit;
	}

	/**
	 * POST-Verarbeitung
	 */
	public function handle_submission(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$form_type_slug = sanitize_text_field( $_POST['form_type'] ?? '' );
		$form = $this->get_form_instance( $form_type_slug );
		$mode = $_POST['submit_mode'] ?? 'check';
		
		$is_valid = $form->validate( $_POST );
		$raw_data   = $_POST; 
		$valid_data = $form->get_data(); 
		$errors     = $form->get_errors();

		if ( 'pdf' === $mode && ! empty( $valid_data['prot_was_corrected'] ) ) {
			$mode = 'check';
			$errors['date_autocorrect'] = 'Achtung: Datum korrigiert (WE/Ferien). Bitte prüfen.';
			$is_valid = false;
		}

		// Start der digitalen Noteneinsammlung: Ohne zugeordnete Lehrkraft mit
		// erreichbarer Adresse gäbe es keinen Empfänger — dann lieber gar nicht
		// starten, statt einen Prozess anzulegen, der still auf niemanden wartet.
		if ( 'collect' === $mode && $is_valid ) {
			$collect_errors = $this->check_collect_preconditions( $valid_data );
			if ( ! empty( $collect_errors ) ) {
				$errors   = array_merge( $errors, $collect_errors );
				$mode     = 'check';
				$is_valid = false;
			}
		}

		if ( 'check' === $mode || ! $is_valid ) {
			$is_success = ( $is_valid && 'check' === $mode );
			$refill_data = array_merge( $raw_data, $valid_data );
			$state = [ 'data' => $refill_data, 'errors' => $errors, 'success' => $is_success ];
			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		$submission_id = (int)( $_POST['submission_id'] ?? 0 );
		$current_user_id = get_current_user_id();
		$entry_id = 0; // Initialisieren

		// LOGIK: Nur speichern, wenn es KEINE Dienstbefreiung ist
		if ( 'service_leave_v1' !== $form_type_slug ) {

			$db_data = [ 
				'form_type' => $form->get_slug(), 
				'status' => 'submitted', 
				'user_id' => $current_user_id, 
				'form_data' => $valid_data 
			];

			if ( $submission_id > 0 ) {
				$this->repository->update( $submission_id, $db_data, $current_user_id );
				$entry_id = $submission_id;
			} else {
				$entry_id = $this->repository->create( $db_data );
			}

			if ( 0 === $entry_id ) wp_die( 'DB Error' );

		} else {
			// FALL: Dienstbefreiung (Wird nicht gespeichert)
			// Wir nutzen eine temporäre "ID" für den Dateinamen (z.B. Uhrzeit)
			$entry_id = (int)date('His');
		}

		// Digitale Noteneinsammlung starten statt PDF ausliefern.
		if ( 'collect' === $mode ) {
			$this->start_grade_collection( $entry_id, $valid_data, $current_user_id );
			exit;
		}

		// PDF Generierung
		$valid_data['entry_id'] = $entry_id;
		$data = $valid_data; 
		ob_start();
		if ( 'service_leave_v1' === $form_type_slug ) {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
		} else {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
			if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
			}
		}
		$final_html = ob_get_clean() . '</body></html>';

		$filename = sprintf('%s_%d_%s%s', date('y-m-d'), $entry_id, ('service_leave_v1' === $form_type_slug ? 'Befreiung_' : 'Abmeldung_'), sanitize_file_name($valid_data['lastname']));
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		exit;
	}

	/**
	 * Admin Aktionen
	 */
	public function handle_admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Forbidden');
		$action = $_GET['mh_admin_action'] ?? '';
		$id = (int)($_GET['id'] ?? 0);
		check_admin_referer( 'mh_admin_action_' . $id );
		if ( 'delete' === $action ) {
			$this->repository->delete_as_admin( $id );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=deleted' ) );
			exit;
		}
		if ( 'download' === $action ) {
			$_GET['mh_action'] = 'download';
			$this->handle_dashboard_action();
			exit;
		}
	}

	/**
	 * Dashboard Aktionen (User)
	 */
	public function handle_dashboard_action(): void {
		if ( ! is_user_logged_in() ) wp_die( 'Forbidden' );
		$action = $_GET['mh_action'] ?? '';
		$id = (int)( $_GET['id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_dashboard_action_' . $id ) ) wp_die( 'Nonce fail' );
		$current_user = get_current_user_id();

		if ( 'delete' === $action ) {
			$this->repository->delete_submission( $id, $current_user );
			wp_redirect( add_query_arg( 'mh_msg', 'deleted', wp_get_referer() ) );
			exit;
		}
		if ( 'download' === $action ) {
			$entry = $this->repository->get_by_id( $id );
			if ( ! $entry || (int)$entry['user_id'] !== $current_user ) wp_die( 'Denied' );
			$valid_data = $entry['form_data'];
			$valid_data['entry_id'] = $id;
			$data = $valid_data;
			ob_start();
			if ( 'service_leave_v1' === $entry['form_type'] ) include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
			else {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
				if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
			}
			$html = ob_get_clean() . '</body></html>';
			$filename = sprintf('%s_%d_%s', date('y-m-d', strtotime($entry['created_at'])), $id, sanitize_file_name($valid_data['lastname']));
			$this->pdf_generator->generate_and_stream( $id, $html, $filename );
			exit;
		}
	}

	/**
	 * Bulk Aktionen (Admin)
	 */
	public function handle_admin_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'bulk-submissions' );
		$action = $_POST['action'] ?? $_POST['action2'] ?? '';
		$ids = array_map( 'intval', $_POST['bulk_ids'] ?? [] );
		if ( 'delete' === $action && ! empty( $ids ) ) {
			$count = $this->repository->delete_multiple( $ids );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=bulk_deleted&count=' . $count ) );
			exit;
		}
	}
	/**
	 * Rendert die Hilfe-Seite im Backend
	 */
	public function render_admin_help(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		include MH_FW_PLUGIN_DIR . 'templates/admin-help.php';
	}
}