<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Model\Form\Form_Interface;
use Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form;
use Mh\FormWorkflows\Model\Form\Service_Leave_Form;

class Form_Controller {

	public function __construct(
		private Submission_Repository $repository,
		private Class_Repository $class_repo,       // Stammdaten
		private Teacher_Repository $teacher_repo,   // Stammdaten
		private Pdf_Generator $pdf_generator
	) {}
	
	/**
	 * Factory: Wählt das passende Model
	 */
	private function get_form_instance( string $type ): Form_Interface {
		return match( $type ) {
			'service_leave_v1'     => new Service_Leave_Form(),
			'abmeldung_student_v1' => new Abmeldung_Student_Form(),
			default                => new Abmeldung_Student_Form(),
		};
	}

	public function render_form( array $attributes = [] ): string {
		$form_type = $attributes['type'] ?? 'abmeldung_student_v1'; 

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
		} 
		// --- NEU: Laden aus einer existierenden ID (Bearbeiten-Modus) ---
		else if ( isset($_GET['mh_edit_id']) ) {
			$edit_id = (int)$_GET['mh_edit_id'];
			$entry = $this->repository->get_by_id($edit_id);
			
			// Sicherheit: Nur laden, wenn es dem User gehört und der Typ stimmt
			if ($entry && (int)$entry['user_id'] === get_current_user_id() && $entry['form_type'] === $form_type) {
				$form_data = $entry['form_data'];
				// Wir markieren für die View, dass wir im "Wiederherstellungs-Modus" sind
				$form_data['is_reloaded'] = true; 
			}
		}

		// Stammdaten laden (unverändert)
		$classes_list = method_exists($this->class_repo, 'get_real_classes') ? $this->class_repo->get_real_classes() : [];
		$teachers_list = method_exists($this->teacher_repo, 'get_all_teachers') ? $this->teacher_repo->get_all_teachers() : [];

		ob_start();
		if ( 'service_leave_v1' === $form_type ) {
			include MH_FW_PLUGIN_DIR . 'templates/form-service-leave.php';
		} else {
			include MH_FW_PLUGIN_DIR . 'templates/form-abmeldung.php';
		}
		return ob_get_clean() ?: '';
	}

	public function handle_submission(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen (Nonce).' );
		}

		// 1. Model wählen
		$form_type_slug = sanitize_text_field( $_POST['form_type'] ?? '' );
		$form = $this->get_form_instance( $form_type_slug );

		$mode = $_POST['submit_mode'] ?? 'check';
		
		// 2. Validierung
		$is_valid = $form->validate( $_POST );
		
		$raw_data   = $_POST; 
		$valid_data = $form->get_data(); 
		$errors     = $form->get_errors();

		// SAFETY INTERCEPT (Für Datumskorrektur bei Abmeldung)
		if ( 'pdf' === $mode && ! empty( $valid_data['prot_was_corrected'] ) ) {
			$mode = 'check';
			$errors['date_autocorrect'] = 'Achtung: Datum wurde automatisch korrigiert (WE/Ferien). Bitte prüfen.';
			$is_valid = false;
		}

		// 3. Fehler / Check Modus
		if ( 'check' === $mode || ! $is_valid ) {
			$is_success = ( $is_valid && 'check' === $mode );
			$refill_data = array_merge( $raw_data, $valid_data );

			$state = [
				'data'    => $refill_data,
				'errors'  => $errors,
				'success' => $is_success
			];

			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// 4. Speichern
		$entry_id = $this->repository->create( [
			'form_type' => $form->get_slug(),
			'status'    => 'submitted',
			'user_id'   => get_current_user_id(),
			'form_data' => $valid_data
		] );

		if ( 0 === $entry_id ) wp_die( 'Datenbankfehler.' );
		  $valid_data['entry_id'] = $entry_id;

		// 5. PDF Weiche
		$data = $valid_data; 
		ob_start();
		
		if ( 'service_leave_v1' === $form_type_slug ) {
			// PDF für Dienstbefreiung
			if ( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php' ) ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
			} else {
				echo "PDF Template 'pdf-service-leave.php' fehlt.";
			}
		} else {
			// PDF für Abmeldung
			if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php' ) ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
			}
			if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
				if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php' ) ) {
					include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
				}
			}
		}
		
		$final_html = ob_get_clean();
		$final_html .= '</body></html>';

		// Dateinamen generieren
		// Format Wunsch: Datum(JJ-MM-TT)_LNR_Befreiung_Nachname-Vorname
		
		$lastname  = sanitize_file_name( $valid_data['lastname'] ?? 'Unbekannt' );
		$firstname = sanitize_file_name( $valid_data['firstname'] ?? '' );
		$today_str = date('y-m-d'); // y = 2-stelliges Jahr (25), m = Monat, d = Tag
		
		if ( 'service_leave_v1' === $form_type_slug ) {
			// Beispiel: 25-12-16_105_Befreiung_Mustermann-Max
			$filename = sprintf( '%s_%d_Befreiung_%s-%s', $today_str, $entry_id, $lastname, $firstname );
		} else {
			// Fallback für Abmeldung (oder auch anpassen, wenn gewünscht)
			$filename = 'Abmeldung_' . $lastname . '_' . $firstname;
		}
		
		// PDF Streamen
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		
		exit;
	}
	/**
	 * Render Methode für das User-Dashboard [mh_my_submissions]
	 */
	public function render_dashboard(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte anmelden, um die Übersicht zu sehen.</p>';
		}

		$user_id = get_current_user_id();
		$submissions = $this->repository->get_submissions_by_user( $user_id );
		
		// Daten nach Schuljahren gruppieren
		// Schuljahr-Wechsel ist immer am 01.08.
		$grouped = [];
		foreach ( $submissions as $sub ) {
			// created_at string zu Timestamp
			$ts = strtotime( $sub['created_at'] );
			$year = (int)date( 'Y', $ts );
			$month = (int)date( 'n', $ts );
			
			// Wenn Monat < 8 (Jan-Juli), gehört es zum Schuljahr, das im Vorjahr begann
			if ( $month < 8 ) {
				$school_year = ($year - 1) . '/' . substr((string)$year, -2);
			} else {
				$school_year = $year . '/' . substr((string)($year + 1), -2);
			}
			
			// JSON decoden für die Anzeige (Name, Art)
			if ( is_string( $sub['form_data'] ) ) {
				$sub['data'] = json_decode( $sub['form_data'], true );
			} else {
				$sub['data'] = $sub['form_data']; // Falls Repository es schon array liefert
			}
			
			$grouped[ $school_year ][] = $sub;
		}

		// Nachrichten abfangen (z.B. "Gelöscht")
		$msg = isset($_GET['mh_msg']) ? sanitize_text_field($_GET['mh_msg']) : '';

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/dashboard-user.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Handelt Aktionen aus dem Dashboard (Download, Delete)
	 */
	public function handle_dashboard_action(): void {
		if ( ! is_user_logged_in() ) wp_die( 'Forbidden' );

		$action = $_GET['mh_action'] ?? '';
		$id     = (int)( $_GET['id'] ?? 0 );
		$nonce  = $_GET['_wpnonce'] ?? '';

		if ( ! wp_verify_nonce( $nonce, 'mh_dashboard_action_' . $id ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$current_user = get_current_user_id();

		// --- LÖSCHEN ---
		if ( 'delete' === $action ) {
			$success = $this->repository->delete_submission( $id, $current_user );
			$msg = $success ? 'deleted' : 'error';
			wp_redirect( remove_query_arg( ['mh_action', 'id', '_wpnonce'], add_query_arg( 'mh_msg', $msg ) ) );
			exit;
		}

		// --- DOWNLOAD (Re-Generate) ---
		if ( 'download' === $action ) {
			// 1. Daten laden
			$entry = $this->repository->get_by_id( $id );
			
			// Sicherheitscheck: Gehört der Eintrag mir?
			if ( ! $entry || (int)$entry['user_id'] !== $current_user ) {
				wp_die( 'Zugriff verweigert.' );
			}

			$valid_data = $entry['form_data']; // Ist durch Repo schon array (wenn du mein Repo 1:1 hast)
			// Sicherheitshalber decoden falls noch JSON String
			if(is_string($valid_data)) $valid_data = json_decode($valid_data, true);

			// ID wieder injizieren für Footer
			$valid_data['entry_id'] = $id; 
			$data = $valid_data;

			// 2. PDF Bauen (gleiche Logik wie submit)
			$form_type = $entry['form_type'];
			
			ob_start();
			if ( 'service_leave_v1' === $form_type ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
			} else {
				// Abmeldung
				include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
				if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
					include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
				}
			}
			$html = ob_get_clean() . '</body></html>';

			// Dateiname: Nutzung des ERSTELLUNGS-DATUMS aus der DB
			// $entry['created_at'] ist z.B. "2025-12-16 10:00:00"
			$created_date = date('y-m-d', strtotime($entry['created_at']));
			$lastname = sanitize_file_name($valid_data['lastname'] ?? 'Dokument');
			
			$prefix = ('service_leave_v1' === $form_type) ? 'Antrag_' : 'Abmeldung_';
			// Neuer Dateiname mit Erstellungsdatum
			$filename = sprintf('%s_%d_%s%s', $created_date, $id, $prefix, $lastname);

			$this->pdf_generator->generate_and_stream( $id, $html, $filename );
			exit;
		}
	}
}