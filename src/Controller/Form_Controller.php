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

	/**
	 * Render Methode (Jetzt mit State-Rehydration)
	 */
	public function render_form( array $attributes = [] ): string {
		// 1. Schauen, ob wir Feedback von einem vorherigen Submit haben
		// Wir nutzen die User ID als Key. Wenn User nicht eingeloggt, bräuchte man Cookie/Session-ID.
		$transient_key = 'mh_fw_state_' . get_current_user_id();
		$state = get_transient( $transient_key );
		
		// 2. State laden oder leeres Array
		$form_data   = [];
		$form_errors = [];
		$is_success  = false;

		if ( false !== $state ) {
			$form_data   = $state['data'] ?? [];
			$form_errors = $state['errors'] ?? [];
			$is_success  = $state['success'] ?? false;
			
			// Transient löschen, damit es beim nächsten Refresh weg ist (Flash Message)
			delete_transient( $transient_key );
		}

		ob_start();
		// Wir reichen die Variablen ($form_data, $form_errors, $is_success) an die View weiter
		include MH_FW_PLUGIN_DIR . 'templates/form-abmeldung.php';
		return ob_get_clean() ?: '';
	}

	public function handle_submission(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$mode = $_POST['submit_mode'] ?? 'check';
		$form = new Abmeldung_Student_Form();
		$is_valid = $form->validate( $_POST );
		
		// Daten holen (entweder sauber oder raw input bei Fehler)
		// Model->get_data() liefert nur saubere Daten. Bei Fehler wollen wir aber den User-Input behalten.
		// Deshalb mergen wir POST für das Refilling, nutzen aber Model-Errors.
		$raw_data = $_POST; 
		$valid_data = $form->get_data(); // Für DB/PDF nutzen wir NUR das

		// === MODUS: NUR PRÜFEN ===
		if ( 'check' === $mode ) {
			
			// Zustand speichern für Redirect
			$state = [
				'data'    => $raw_data,
				'errors'  => $form->get_errors(),
				'success' => $is_valid
			];

			// Speichere für 60 Sekunden
			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );

			// Redirect zurück zum Formular (gleiche Seite)
			wp_redirect( wp_get_referer() );
			exit;
		}

		// === MODUS: PDF (Wenn Validierung OK) ===
		if ( $is_valid ) {
			// Speichern
			$entry_id = $this->repository->create( [
				'form_type' => $form->get_slug(),
				'status'    => 'submitted',
				'user_id'   => get_current_user_id(),
				'form_data' => $valid_data
			] );

			// PDF Generieren (Kein Redirect, direkter Stream)
			$data = $valid_data;
			ob_start();
			if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php' ) ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
			}
			$pdf_html = ob_get_clean();

			$this->pdf_generator->generate_and_stream( $entry_id, $pdf_html, 'Abmeldung_' . $valid_data['lastname'] );
			exit;
		} else {
			// PDF gedrückt, aber Fehler -> Zurückleiten mit Fehlern
			$state = [
				'data'    => $raw_data,
				'errors'  => $form->get_errors(),
				'success' => false
			];
			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}
	}
}