<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Model\Form\Form_Interface;
use Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form;
use Mh\FormWorkflows\Model\Form\Service_Leave_Form;

class Form_Controller {

	/**
	 * Konstruktor mit allen 5 Abhängigkeiten (muss zur Bootstrap passen!)
	 */
	public function __construct(
		private Submission_Repository $repository,
		private Class_Repository $class_repo,
		private Teacher_Repository $teacher_repo,
		private Student_Repository $student_repo,
		private Pdf_Generator $pdf_generator
	) {}
	
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
		$db_data = [ 'form_type' => $form->get_slug(), 'status' => 'submitted', 'user_id' => $current_user_id, 'form_data' => $valid_data ];

		if ( $submission_id > 0 ) {
			$this->repository->update( $submission_id, $db_data, $current_user_id );
			$entry_id = $submission_id;
		} else {
			$entry_id = $this->repository->create( $db_data );
		}

		if ( 0 === $entry_id ) wp_die( 'DB Error' );

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
}