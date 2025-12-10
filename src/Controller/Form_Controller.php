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

	/**
	 * Verarbeitet den POST-Request beim Absenden.
	 */
	public function handle_submission(): void {
		// 1. Security Check (Nonce)
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen (Nonce).' );
		}

		// Welcher Button wurde gedrückt? (check vs. pdf)
		$mode = $_POST['submit_mode'] ?? 'check';

		// 2. Das konkrete Formular-Model laden
		$form = new Abmeldung_Student_Form();

		// 3. Validieren
		// $is_valid ist true, wenn keine Fehler gefunden wurden
		$is_valid = $form->validate( $_POST );
		
		// Daten holen
		// raw_data: Was der User eingetippt hat (für Refill bei Fehlern)
		// valid_data: Die sauberen, geprüften Daten aus dem Model (für DB/PDF)
		$raw_data   = $_POST; 
		$valid_data = $form->get_data();
		$errors     = $form->get_errors();

		// === SZENARIO 1: "Nur Prüfen" ODER Validierungsfehler ===
		// In beiden Fällen leiten wir zurück zum Formular und zeigen Boxen an.
		if ( 'check' === $mode || ! $is_valid ) {
			
			// Status bestimmen: Erfolgreich nur, wenn valide UND Modus 'check' war
			$is_success = ( $is_valid && 'check' === $mode );

			// Zustand in Transient speichern (Flash Message Pattern)
			// Key basiert auf User-ID, damit Sessions sich nicht mischen
			$state = [
				'data'    => $raw_data,
				'errors'  => $errors,
				'success' => $is_success
			];

			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 ); // 60 Sek gültig

			// Redirect zurück zur gleichen Seite
			wp_redirect( wp_get_referer() );
			exit;
		}

		// === SZENARIO 2: PDF Erstellen (Validierung war OK) ===
		
		// 4. In Datenbank speichern
		$entry_id = $this->repository->create( [
			'form_type' => $form->get_slug(),
			'status'    => 'submitted',
			'user_id'   => get_current_user_id(),
			'form_data' => $valid_data
		] );

		if ( 0 === $entry_id ) {
			wp_die( 'Kritischer Fehler beim Speichern in die Datenbank.' );
		}

		// 5. PDF HTML Generieren
		$data = $valid_data; 
		
		// A) Hauptformular (startet mit <html><body>, aber wir haben </body></html> entfernt!)
		ob_start();
		if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php' ) ) {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
		}
		$final_html = ob_get_clean();

		// B) Protokoll Anhängen (Nur Inhalt, keine <html> Tags)
		if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
			ob_start();
			if( file_exists( MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php' ) ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
			}
			$final_html .= ob_get_clean();
		}

		// WICHTIG: Jetzt schließen wir das Dokument sauber ab!
		$final_html .= '</body></html>';

		// 6. PDF an Browser senden
		$filename = 'Abmeldung_' . sanitize_file_name( $valid_data['lastname'] );
		
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		
		exit;
	}
}