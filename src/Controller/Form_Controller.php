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
			
			// Sicherheitscheck: Passt der State zum aktuellen Formular?
			if(isset($form_data['form_type']) && $form_data['form_type'] !== $form_type) {
				$form_data = []; $form_errors = []; $is_success = false;
			} else {
				delete_transient( $transient_key );
			}
		}

		// STAMMDATEN LADEN
		// 1. Klassen (für Abmeldung)
		$classes_list = [];
		if ( method_exists($this->class_repo, 'get_real_classes') ) {
			$classes_list = $this->class_repo->get_real_classes();
		}

		// 2. Lehrer (für Dienstbefreiung Dropdown)
		$teachers_list = [];
		if ( method_exists($this->teacher_repo, 'get_all_teachers') ) {
			$teachers_list = $this->teacher_repo->get_all_teachers();
		}


		ob_start();
		
		// Template Weiche
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
}