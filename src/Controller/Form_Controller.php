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
				$form_data['id'] = $entry['id'];
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
		// Prüfen, ob wir eine ID zum Updaten haben
		$submission_id = (int)( $_POST['submission_id'] ?? 0 );
		$current_user_id = get_current_user_id();

		$db_data = [
			'form_type' => $form->get_slug(),
			'status'    => 'submitted',
			'user_id'   => $current_user_id,
			'form_data' => $valid_data
		];

		if ( $submission_id > 0 ) {
			// UPDATE statt Create
			$this->repository->update( $submission_id, $db_data, $current_user_id );
			$entry_id = $submission_id;
		} else {
			// NEU ANLEGEN
			$entry_id = $this->repository->create( $db_data );
		}

		if ( 0 === $entry_id ) wp_die( 'Datenbankfehler.' );

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
		// Wir nutzen updated_at (oder NOW, da wir gerade speichern)
    $current_date = date('y-m-d'); 
    
    // Wenn wir eine ID haben (Update), nutzen wir diese, sonst die neue entry_id
    $lnr = $submission_id > 0 ? $submission_id : $entry_id;

    $filename = sprintf('%s_%d_%s%s', 
        $current_date, 
        $lnr, 
        ('service_leave_v1' === $form_type_slug ? 'Befreiung_' : 'Abmeldung_'),
        sanitize_file_name($valid_data['lastname'])
    );
		// PDF Streamen
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		
		exit;
	}
	/**
	 * Render Methode für das User-Dashboard [mh_my_submissions]
	 */
	    public function render_dashboard(): string {
        if ( ! is_user_logged_in() ) return '<p>Bitte anmelden.</p>';

        $user_id = get_current_user_id();
        $submissions = $this->repository->get_submissions_by_user( $user_id );
        
        // URLs für alle bekannten Typen vorbereiten
        $urls = [
            'service_leave_v1'     => $this->get_url_for_form_type('service_leave_v1'),
            'abmeldung_student_v1' => $this->get_url_for_form_type('abmeldung_student_v1'),
            'abmeldung'            => $this->get_url_for_form_type('abmeldung_student_v1'), // Alias
        ];

        // Gruppierung nach Schuljahr
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
        // WICHTIG: Hier stellen wir sicher, dass $urls und $grouped für das Template sichtbar sind
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
	/**
	 * Render Methode für das Admin-Dashboard im Backend
	 */
	public function render_admin_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Filter aus der URL holen und sanitisieren
		$filters = [
			'start_date' => sanitize_text_field( $_GET['start_date'] ?? '' ),
			'end_date'   => sanitize_text_field( $_GET['end_date'] ?? '' ),
			'user_id'    => sanitize_text_field( $_GET['user_id'] ?? '' ),
			'form_type'  => sanitize_text_field( $_GET['form_type'] ?? '' ),
		];

		// Daten holen
		$submissions = $this->repository->get_filtered_submissions( $filters );
		$submitters  = $this->repository->get_distinct_submitters();
		
		foreach ( $submissions as &$sub ) {
			$sub['data'] = is_string($sub['form_data']) ? json_decode($sub['form_data'], true) : $sub['form_data'];
		}

		include MH_FW_PLUGIN_DIR . 'templates/dashboard-admin.php';
	}

	/**
	 * Handelt Admin-Aktionen (Löschen/Download)
	 */
	public function handle_admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Keine Berechtigung');

		$action = $_GET['mh_admin_action'] ?? '';
		$id     = (int)($_GET['id'] ?? 0);

		check_admin_referer( 'mh_admin_action_' . $id );

		if ( 'delete' === $action ) {
			$this->repository->delete_as_admin( $id );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=deleted' ) );
			exit;
		}

		if ( 'download' === $action ) {
            // Wir nutzen die gleiche Logik wie im User-Dashboard
			$_GET['mh_action'] = 'download'; // "Fake" den User-Download-Trigger
            $this->handle_dashboard_action(); 
            exit;
		}
	}
	
	/**
	 * Verarbeitet Mehrfachaktionen (Bulk Actions)
	 */
	public function handle_admin_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		// Sicherheitsprüfung
		check_admin_referer( 'bulk-submissions' );

		$action = $_POST['action'] ?? $_POST['action2'] ?? '';
		$ids    = array_map( 'intval', $_POST['bulk_ids'] ?? [] );

		if ( 'delete' === $action && ! empty( $ids ) ) {
			$count = $this->repository->delete_multiple( $ids );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=bulk_deleted&count=' . $count ) );
			exit;
		}
	}
	
	/**
     * Holt die URL für einen Formular-Typ aus den Admin-Einstellungen.
     */
    private function get_url_for_form_type(string $type): string {
        $options = get_option( 'mh_fw_settings', [] );
        
        // Wir mappen hier zur Sicherheit auch alte oder kurze Typen auf die neuen Keys
        $key = 'page_id_' . $type;
        if ($type === 'abmeldung') $key = 'page_id_abmeldung_student_v1'; // Fallback für alte Testdaten

        $page_id = (int)($options[$key] ?? 0);

        if ($page_id > 0) {
            $url = get_permalink($page_id);
            return $url ? $url : '';
        }

        return ''; 
    }
}