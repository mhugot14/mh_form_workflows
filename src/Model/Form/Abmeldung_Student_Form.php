<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

use Mh\FormWorkflows\Service\School_Date_Calculator;

class Abmeldung_Student_Form extends Abstract_Form {

	public function get_slug(): string {
		return 'abmeldung_student_v1';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$calc = new School_Date_Calculator();

		// --- 1. DATEN HOLEN & KORRIGIEREN ---
		
		// A) Abmeldedatum (Bleibt wie eingegeben)
		$date_off_raw = $this->sanitize_text( $data['date_off'] ?? '' );
		// Wichtig: Hier NICHT korrigieren, User-Eingabe zählt für Kündigungsdatum
		$date_off = $date_off_raw; 
		
		// B) Konferenzdatum (Automatische Korrektur auf Schultag)
		$prot_date_raw = $this->sanitize_text( $data['prot_date'] ?? '' );
		$prot_date     = $calc->get_previous_school_day( $prot_date_raw );
		
		// C) Check: Wurde korrigiert?
		$date_was_corrected = false;
		if ( ! empty( $prot_date_raw ) && $prot_date_raw !== $prot_date ) {
			$date_was_corrected = true;
		}

		// Ausgabedatum ist identisch mit Konferenzdatum
		$prot_issue_date = $prot_date;

		// AV-Klasse Startdatum auch korrigieren (optional, aber sinnvoll)
		$av_date_raw   = $this->sanitize_text( $data['av_date_start'] ?? '' );
		$av_date_start = $calc->get_previous_school_day( $av_date_raw );

		// --- RESTLICHE INPUTS ---
		$name      = $this->sanitize_text( $data['lastname'] ?? '' );
		$firstname = $this->sanitize_text( $data['firstname'] ?? '' );
		$dob       = $this->sanitize_text( $data['dob'] ?? '' );
		$class     = $this->sanitize_text( $data['class_name'] ?? '' );
		$teacher   = $this->sanitize_text( $data['teacher'] ?? '' );
		$reason    = $this->sanitize_text( $data['reason'] ?? '' );
		$new_school = $this->sanitize_text( $data['new_school'] ?? '' );
		
		$compulsory      = $this->sanitize_text( $data['compulsory'] ?? '' );
		$av_talk_with    = $this->sanitize_text( $data['av_talk_with'] ?? '' );
		$av_talk_date    = $this->sanitize_text( $data['av_talk_date'] ?? '' );
		$education_track = $this->sanitize_text( $data['new_education_track'] ?? '' );
		
		$is_minor = ( isset( $data['is_minor'] ) && '1' === $data['is_minor'] );
		$protocol = isset( $data['protocol_attached'] ) ? '1' : '0';

		$certificate  = $this->sanitize_text( $data['certificate'] ?? '' );
		$missed_hours = (int) ( $data['missed_hours'] ?? 0 );
		$missed_ue    = (int) ( $data['missed_ue'] ?? 0 );
		$missed_hours_raw = trim( (string) ( $data['missed_hours'] ?? '' ) ); // Für Leere-Prüfung
		$missed_ue_raw    = trim( (string) ( $data['missed_ue'] ?? '' ) );

		$prot_type       = $this->sanitize_text( $data['prot_type'] ?? '' );
		$prot_chair      = $this->sanitize_text( $data['prot_chair'] ?? '' );
		$prot_room       = $this->sanitize_text( $data['prot_room'] ?? '' );
		$prot_remarks    = sanitize_textarea_field( $data['prot_remarks'] ?? '' );
		$prot_end_school = $this->sanitize_text( $data['prot_end_school'] ?? '' );
		$prot_transfer   = $this->sanitize_text( $data['prot_transfer'] ?? '' );
		$prot_check_comp = isset( $data['prot_check_compulsory'] ) ? '1' : '0';

		$future_secured = $this->sanitize_text( $data['future_secured'] ?? '' );

		// --- 2. VALIDIERUNG (Pflichtfelder wiederhergestellt!) ---
		if ( empty( $name ) ) $this->add_error( 'lastname', 'Nachname fehlt.' );
		if ( empty( $firstname ) ) $this->add_error( 'firstname', 'Vorname fehlt.' );
		if ( empty( $dob ) ) $this->add_error( 'dob', 'Geburtsdatum fehlt.' );
		if ( empty( $class ) ) $this->add_error( 'class_name', 'Klasse fehlt.' );
		if ( empty( $teacher ) ) $this->add_error( 'teacher', 'Klassenlehrer/in (Kürzel) fehlt.' );
		if ( empty( $date_off ) ) $this->add_error( 'date_off', 'Datum der Abmeldung fehlt.' );
		if ( empty( $reason ) ) $this->add_error( 'reason', 'Grund der Abmeldung auswählen.' );
		if ( empty( $compulsory ) ) $this->add_error( 'compulsory', 'Angabe zur Schulpflicht fehlt.' );
		if ( empty( $future_secured ) ) {
            $this->add_error( 'future_secured', 'Angabe zur weiteren Laufbahn fehlt.' );
        }
		// Conditional Logic
		if ( 'schulwechsel' === $reason && empty( $new_school ) ) $this->add_error( 'new_school', 'Bitte Namen der neuen Schule angeben.' );
		if ( 'av_klasse' === $compulsory && empty( $av_date_start ) ) $this->add_error( 'av_date_start', 'AV-Klasse: Startdatum fehlt.' );
		if ( 'bildungsgang' === $compulsory && empty( $education_track ) ) $this->add_error( 'new_education_track', 'Bitte Bildungsgang angeben.' );
		
		if ( 'ueberweisung' === $certificate ) {
			if ( '' === $missed_hours_raw ) $this->add_error( 'missed_hours', 'Bitte Gesamtfehlstunden angeben.' );
			if ( $missed_ue > $missed_hours ) $this->add_error( 'missed_ue', 'Unentschuldigte Stunden können nicht höher sein als Gesamtfehlstunden.' );
		}

		if ( '1' === $protocol ) {
			if ( empty( $prot_type ) ) $this->add_error( 'prot_type', 'Bitte Typ für Protokoll wählen.' );
			// Wir prüfen hier prot_date (das korrigierte), nicht raw. Wenn leer -> Fehler.
			if ( empty( $prot_date ) ) $this->add_error( 'prot_date', 'Konferenzdatum fehlt.' );
			if ( empty( $prot_chair ) ) $this->add_error( 'prot_chair', 'Vorsitzende/r fehlt.' );
		}


		// --- 3. DATEN SPEICHERN ---
		$this->data = [
			'lastname'            => $name,
			'firstname'           => $firstname,
			'dob'                 => $dob,
			'class_name'          => $class,
			'teacher'             => $teacher,
			'is_minor'            => $is_minor,
			
			'date_off'            => $date_off,  // User Input
			
			'prot_date'           => $prot_date, // KORRIGIERT
			'prot_issue_date'     => $prot_issue_date,
			'prot_was_corrected'  => $date_was_corrected, // Das Flag für die GUI!
			'prot_remarks'        => $prot_remarks,
			
			'av_date_start'       => $av_date_start,
			
			'reason'              => $reason,
			'new_school'          => $new_school,
			'compulsory'          => $compulsory,
			'av_talk_with'        => $av_talk_with,
			'av_talk_date'        => $av_talk_date,
			'new_education_track' => $education_track,
			'certificate'         => $certificate,
			'missed_hours'        => $missed_hours,
			'missed_ue'           => $missed_ue,
			'protocol_attached'   => $protocol,
			'prot_type'           => $prot_type,
			'prot_chair'          => $prot_chair,
			'prot_room'           => $prot_room,
			'prot_end_school'     => $prot_end_school,
			'prot_transfer'       => $prot_transfer,
			'prot_check_comp'     => $prot_check_comp,
			'future_secured'      => $future_secured,
		];

		return empty( $this->errors );
	}
}