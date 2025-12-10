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

		// --- BESTEHENDE LOGIK (IDENTISCH ZU VORHER) ---
		$name      = $this->sanitize_text( $data['lastname'] ?? '' );
		$firstname = $this->sanitize_text( $data['firstname'] ?? '' );
		$dob       = $this->sanitize_text( $data['dob'] ?? '' );
		$class     = $this->sanitize_text( $data['class_name'] ?? '' );
		$teacher   = $this->sanitize_text( $data['teacher'] ?? '' );
		$date_off  = $this->sanitize_text( $data['date_off'] ?? '' );
		$reason    = $this->sanitize_text( $data['reason'] ?? '' );
		$new_school = $this->sanitize_text( $data['new_school'] ?? '' );
		$compulsory      = $this->sanitize_text( $data['compulsory'] ?? '' );
		$av_date_start   = $this->sanitize_text( $data['av_date_start'] ?? '' );
		$av_talk_with    = $this->sanitize_text( $data['av_talk_with'] ?? '' );
		$av_talk_date    = $this->sanitize_text( $data['av_talk_date'] ?? '' );
		$education_track = $this->sanitize_text( $data['new_education_track'] ?? '' );
		$protocol = isset( $data['protocol_attached'] ) ? '1' : '0';
		$is_minor = ( isset( $data['is_minor'] ) && '1' === $data['is_minor'] );
		$certificate = $this->sanitize_text( $data['certificate'] ?? '' );
		$missed_hours_raw = trim( (string) ( $data['missed_hours'] ?? '' ) );
		$missed_ue_raw    = trim( (string) ( $data['missed_ue'] ?? '' ) );
		$missed_hours = (int) $missed_hours_raw;
		$missed_ue    = (int) $missed_ue_raw;

		// --- PFLICHTFELDER BASIS ---
		if ( empty( $name ) ) $this->add_error( 'lastname', 'Nachname fehlt.' );
		if ( empty( $firstname ) ) $this->add_error( 'firstname', 'Vorname fehlt.' );
		if ( empty( $dob ) ) $this->add_error( 'dob', 'Geburtsdatum fehlt.' );
		if ( empty( $class ) ) $this->add_error( 'class_name', 'Klasse fehlt.' );
		if ( empty( $date_off ) ) $this->add_error( 'date_off', 'Abmeldedatum fehlt.' );
		if ( empty( $reason ) ) $this->add_error( 'reason', 'Grund der Abmeldung auswählen.' );
		if ( empty( $compulsory ) ) $this->add_error( 'compulsory', 'Angabe zur Schulpflicht fehlt.' );

		// Conditional Logic Abmeldung
		if ( 'schulwechsel' === $reason && empty( $new_school ) ) $this->add_error( 'new_school', 'Bitte Namen der neuen Schule angeben.' );
		if ( 'av_klasse' === $compulsory && empty( $av_date_start ) ) $this->add_error( 'av_date_start', 'AV-Klasse: Startdatum fehlt.' );
		if ( 'bildungsgang' === $compulsory && empty( $education_track ) ) $this->add_error( 'new_education_track', 'Bitte Bildungsgang angeben.' );
		if ( 'ueberweisung' === $certificate ) {
			if ( '' === $missed_hours_raw ) $this->add_error( 'missed_hours', 'Bitte Gesamtfehlstunden angeben.' );
			if ( $missed_ue > $missed_hours ) $this->add_error( 'missed_ue', 'Plausibilität prüfen.' );
		}

		// --- NEU: PROTOKOLL VALIDIERUNG ---
		$prot_type       = $this->sanitize_text( $data['prot_type'] ?? '' );
		$prot_date       = $this->sanitize_text( $data['prot_date'] ?? '' );
		$prot_chair      = $this->sanitize_text( $data['prot_chair'] ?? '' );
		$prot_room       = $this->sanitize_text( $data['prot_room'] ?? '' );
		
		// Vollzeit spezifisch
		$prot_end_school = $this->sanitize_text( $data['prot_end_school'] ?? '' ); // Ende Schulverhältnis
		$prot_transfer   = $this->sanitize_text( $data['prot_transfer'] ?? '' );   // Überwiesen an
		$prot_check_comp = isset( $data['prot_check_compulsory'] ) ? '1' : '0';    // Schulpflicht überprüft?

		if ( '1' === $protocol ) {
			if ( empty( $prot_type ) ) $this->add_error( 'prot_type', 'Bitte Schultyp für Protokoll wählen (Vollzeit/Berufsschule).' );
			if ( empty( $prot_date ) ) $this->add_error( 'prot_date', 'Konferenzdatum fehlt.' );
			if ( empty( $prot_chair ) ) $this->add_error( 'prot_chair', 'Vorsitzende/r fehlt.' );

			// Wenn Vollzeit:
			if ( 'vollzeit' === $prot_type ) {
				// Hier ggf. Pflichtfelder definieren, z.B. Ende Schulverhältnis?
				// if( empty( $prot_end_school ) ) $this->add_error('prot_end_school', 'Ende des Schulverhältnisses fehlt.');
			}
		}

		// 4. Daten speichern
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
			
			// NEU: Protokoll Daten
			'prot_type'           => $prot_type,
			'prot_date'           => $prot_date,
			'prot_chair'          => $prot_chair,
			'prot_room'           => $prot_room,
			'prot_end_school'     => $prot_end_school,
			'prot_transfer'       => $prot_transfer,
			'prot_check_comp'     => $prot_check_comp,
		];

		return empty( $this->errors );
	}
}