<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

class Abmeldung_Student_Form extends Abstract_Form {

	public function get_slug(): string {
		return 'abmeldung_student_v1';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		// 1. Inputs holen & reinigen (Basisdaten)
		$name      = $this->sanitize_text( $data['lastname'] ?? '' );
		$firstname = $this->sanitize_text( $data['firstname'] ?? '' );
		$dob       = $this->sanitize_text( $data['dob'] ?? '' );
		$class     = $this->sanitize_text( $data['class_name'] ?? '' );
		$date_off  = $this->sanitize_text( $data['date_off'] ?? '' );
		$reason    = $this->sanitize_text( $data['reason'] ?? '' );
		
		// Optionalere Felder
		$new_school = $this->sanitize_text( $data['new_school'] ?? '' );
		$teacher    = $this->sanitize_text( $data['teacher'] ?? '' );

		// NEUE Felder (Schulpflicht & Zeugnis)
		$compulsory      = $this->sanitize_text( $data['compulsory'] ?? '' );
		$av_date_start   = $this->sanitize_text( $data['av_date_start'] ?? '' );
		$av_talk_with    = $this->sanitize_text( $data['av_talk_with'] ?? '' );
		$av_talk_date    = $this->sanitize_text( $data['av_talk_date'] ?? '' );
		$education_track = $this->sanitize_text( $data['new_education_track'] ?? '' );
		
		// Checkboxen geben "1" zurück, wenn gewählt, sonst existieren sie oft gar nicht im Array
		$protocol = isset( $data['protocol_attached'] ) ? '1' : '0';
		$is_minor = ( isset( $data['is_minor'] ) && '1' === $data['is_minor'] );

		$certificate  = $this->sanitize_text( $data['certificate'] ?? '' );
		$missed_hours = (int) ( $data['missed_hours'] ?? 0 );
		$missed_ue    = (int) ( $data['missed_ue'] ?? 0 );


		// 2. Pflichtfelder Basis prüfen
		if ( empty( $name ) ) $this->add_error( 'lastname', 'Nachname fehlt.' );
		if ( empty( $firstname ) ) $this->add_error( 'firstname', 'Vorname fehlt.' );
		if ( empty( $dob ) ) $this->add_error( 'dob', 'Geburtsdatum fehlt.' );
		if ( empty( $class ) ) $this->add_error( 'class_name', 'Klasse fehlt.' );
		if ( empty( $date_off ) ) $this->add_error( 'date_off', 'Abmeldedatum fehlt.' );
		if ( empty( $reason ) ) $this->add_error( 'reason', 'Grund der Abmeldung auswählen.' );
		if ( empty( $compulsory ) ) $this->add_error( 'compulsory', 'Angabe zur Schulpflicht fehlt.' );

		// 3. Logik-Checks (Plausibilität)

		// Wenn "Schulwechsel", dann Name der Schule Pflicht
		if ( 'schulwechsel' === $reason && empty( $new_school ) ) {
			$this->add_error( 'new_school', 'Bitte Namen der neuen Schule angeben.' );
		}
		
		// Wenn "AV-Klasse", dann Startdatum sinnvoll? (Optional, hier nur als Beispiel)
		if ( 'av_klasse' === $compulsory && empty( $av_date_start ) ) {
			// Könnte man als Fehler werten:
			// $this->add_error( 'av_date_start', 'Startdatum für AV-Klasse fehlt.' );
		}

		// Fehlstunden Logik
		if ( 'ueberweisung' === $certificate ) {
			if ( $missed_ue > $missed_hours ) {
				$this->add_error( 'missed_ue', 'Unentschuldigte Stunden können nicht höher sein als Gesamtfehlstunden.' );
			}
		}

		// 4. Daten speichern (Mapping für Repository/PDF)
		$this->data = [
			'lastname'            => $name,
			'firstname'           => $firstname,
			'dob'                 => $dob,
			'class_name'          => $class,
			'teacher'             => $teacher,
			'is_minor'            => $is_minor,
			'date_off'            => $date_off,
			
			'reason'              => $reason,
			'new_school'          => $new_school,
			
			'compulsory'          => $compulsory,
			'av_date_start'       => $av_date_start,
			'av_talk_with'        => $av_talk_with,
			'av_talk_date'        => $av_talk_date,
			'new_education_track' => $education_track,
			
			'certificate'         => $certificate,
			'protocol_attached'   => $protocol,
			'missed_hours'        => $missed_hours,
			'missed_ue'           => $missed_ue,
		];

		// Validierung ist erfolgreich, wenn errors leer ist
		return empty( $this->errors );
	}
}