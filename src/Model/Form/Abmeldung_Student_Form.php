<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

class Abmeldung_Student_Form extends Abstract_Form {

	public function get_slug(): string {
		return 'abmeldung_student_v1';
	}

	// ... (Code davor bleibt gleich)

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		// ... (Sanitizing und Basic Checks bleiben gleich) ... 
		// (Ich kürze hier ab, bitte behalte deine existierenden Checks!)
        
        // HIER NUR DER NEUE CODE:
        $reason = $this->sanitize_text( $data['reason'] ?? '' );
		$new_school = $this->sanitize_text( $data['new_school'] ?? '' );
        
        // --- DEINE EXISTIERENDEN BASIS CHECKS HIER LASSEN ---
        // (Nachname, Vorname, Geburtsdatum, etc.)

		// --- NEU: Logik für Fehlstunden ---
		$missed_hours = (int) ($data['missed_hours'] ?? 0);
		$missed_ue    = (int) ($data['missed_ue'] ?? 0);
		$certificate  = $this->sanitize_text( $data['certificate'] ?? '' );

		if ( 'ueberweisung' === $certificate ) {
			if ( $missed_ue > $missed_hours ) {
				$this->add_error( 'missed_ue', 'Unentschuldigte Stunden können nicht höher sein als Gesamtfehlstunden.' );
			}
		}

		// --- NEU: Daten Array mit korrekten Integern speichern
		$this->data = [
			// ... (alte Felder wie lastname, firstname etc. hier behalten)
            'lastname'   => $this->sanitize_text( $data['lastname'] ?? '' ), // sicherheitshalber hier nochmal explizit
            'firstname'  => $this->sanitize_text( $data['firstname'] ?? '' ),
            'dob'        => $this->sanitize_text( $data['dob'] ?? '' ),
            'class_name' => $this->sanitize_text( $data['class_name'] ?? '' ),
            'teacher'    => $this->sanitize_text( $data['teacher'] ?? '' ),
            'date_off'   => $this->sanitize_text( $data['date_off'] ?? '' ),
            'reason'     => $reason,
            'new_school' => $new_school,
            'is_minor'   => ( isset( $data['is_minor'] ) && '1' === $data['is_minor'] ),
            'compulsory' => $this->sanitize_text( $data['compulsory'] ?? '' ),
            'certificate'=> $certificate,
			'missed_hours' => $missed_hours,
			'missed_ue'    => $missed_ue,
		];

		return empty( $this->errors );
	}
}