<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form;

class Form_Controller {

	public function __construct(
		private Submission_Repository $repository,
		private Pdf_Generator $pdf_generator
	) {}

	public function render_form( array $attributes = [] ): string {
		$transient_key = 'mh_fw_state_' . get_current_user_id();
		$state = get_transient( $transient_key );
		
		$form_data   = [];
		$form_errors = [];
		$is_success  = false;

		if ( false !== $state ) {
			$form_data   = $state['data'] ?? [];
			$form_errors = $state['errors'] ?? [];
			$is_success  = $state['success'] ?? false;
			delete_transient( $transient_key );
		}

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/form-abmeldung.php';
		return ob_get_clean() ?: '';
	}

	public function handle_submission(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen (Nonce).' );
		}

		$mode = $_POST['submit_mode'] ?? 'check';
		$form = new Abmeldung_Student_Form();
		$is_valid = $form->validate( $_POST );
		
		$raw_data   = $_POST; 
		$valid_data = $form->get_data(); // Enthält korrigierte Daten & Flags
		$errors     = $form->get_errors();

		// === NEU: SAFETY INTERCEPT (Sicherheitsbremse) ===
		// Wenn PDF angefordert wurde, aber eine Datumskorrektur stattfand,
		// brechen wir ab und zwingen den User, das neue Datum zu sehen.
		if ( 'pdf' === $mode && ! empty( $valid_data['prot_was_corrected'] ) ) {
			// Wir degradieren den Modus zu "check" -> Seite lädt neu mit Hinweis
			$mode = 'check';
			
			// Wir fügen eine Fehlermeldung hinzu, damit der User weiß, warum kein PDF kam
			$errors['date_autocorrect'] = '<strong>Achtung:</strong> Das Datum lag am Wochenende oder in den Ferien. Es wurde automatisch korrigiert. Bitte prüfen Sie das neue Datum unten im Protokoll und klicken Sie erneut auf Erstellen.';
			
			// Validierung auf false setzen, damit die Fehlerbox rot erscheint
			$is_valid = false;
		}

		// === SZENARIO 1: Nur Prüfen (oder durch Safety Intercept ausgelöst) ===
		if ( 'check' === $mode || ! $is_valid ) {
			
			$is_success = ( $is_valid && 'check' === $mode );

			// DATEN ZUSAMMENFÜHREN
			// Wir nehmen die Valid Data (vom Model korrigiertes Datum),
			// füllen aber fehlende Felder (bei Fehlern) mit Raw Data auf.
			$refill_data = array_merge( $raw_data, $valid_data );

			$state = [
				'data'    => $refill_data, // Wichtig: Hier stehen jetzt "12.12." statt "14.12." drin
				'errors'  => $errors,
				'success' => $is_success
			];

			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// === SZENARIO 2: PDF Erstellen ===
		
		$entry_id = $this->repository->create( [
			'form_type' => $form->get_slug(),
			'status'    => 'submitted',
			'user_id'   => get_current_user_id(),
			'form_data' => $valid_data
		] );

		if ( 0 === $entry_id ) wp_die( 'DB Error' );

		$data = $valid_data; 
		
		ob_start();
		if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php' ) ) {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
		}
		$final_html = ob_get_clean();

		if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
			ob_start();
			if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php' ) ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
			}
			$final_html .= ob_get_clean();
		}
		
		// PDF Sauber schließen
		$final_html .= '</body></html>';

		$filename = 'Abmeldung_' . sanitize_file_name( $valid_data['lastname'] );
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		exit;
	}
}