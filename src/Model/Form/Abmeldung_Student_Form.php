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

		// 1. Inputs holen & reinigen
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
		
		// Bei Zahlenfeldern holen wir uns für die Prüfung auch den Rohwert (String),
		// um zwischen "0" (eingegeben) und "" (leer) zu unterscheiden.
		$missed_hours_raw = trim( (string) ( $data['missed_hours'] ?? '' ) );
		$missed_ue_raw    = trim( (string) ( $data['missed_ue'] ?? '' ) );

		// Casting für spätere Speicherung
		$missed_hours = (int) $missed_hours_raw;
		$missed_ue    = (int) $missed_ue_raw;


		// 2. Grundlegende Pflichtfelder
		if ( empty( $name ) ) $this->add_error( 'lastname', 'Nachname fehlt.' );
		if ( empty( $firstname ) ) $this->add_error( 'firstname', 'Vorname fehlt.' );
		if ( empty( $dob ) ) $this->add_error( 'dob', 'Geburtsdatum fehlt.' );
		if ( empty( $class ) ) $this->add_error( 'class_name', 'Klasse fehlt.' );
		if ( empty( $date_off ) ) $this->add_error( 'date_off', 'Abmeldedatum fehlt.' );
		if ( empty( $reason ) ) $this->add_error( 'reason', 'Grund der Abmeldung auswählen.' );
		if ( empty( $compulsory ) ) $this->add_error( 'compulsory', 'Angabe zur Schulpflicht fehlt.' );

		// 3. BEDINGTE PFLICHTFELDER (Conditional Logic)

		// A) Schulwechsel -> Schule Pflicht
		if ( 'schulwechsel' === $reason && empty( $new_school ) ) {
			$this->add_error( 'new_school', 'Bitte Namen und Ort der neuen Schule angeben.' );
		}
		
		// B) AV-Klasse -> Details Pflicht
		if ( 'av_klasse' === $compulsory ) {
			if ( empty( $av_date_start ) ) $this->add_error( 'av_date_start', 'AV-Klasse: Startdatum fehlt.' );
			if ( empty( $av_talk_with ) ) $this->add_error( 'av_talk_with', 'AV-Klasse: Gesprächspartner fehlt.' );
			if ( empty( $av_talk_date ) ) $this->add_error( 'av_talk_date', 'AV-Klasse: Gesprächsdatum fehlt.' );
		}

		// C) Bildungsgang -> Name Pflicht
		if ( 'bildungsgang' === $compulsory && empty( $education_track ) ) {
			$this->add_error( 'new_education_track', 'Bitte den Namen des neuen Bildungsgangs angeben.' );
		}

		// D) Überweisungszeugnis -> Fehlstunden Pflicht
		if ( 'ueberweisung' === $certificate ) {
			// Wir prüfen den RAW string. Wenn leer -> Fehler. Wenn "0" -> OK.
			if ( '' === $missed_hours_raw ) {
				$this->add_error( 'missed_hours', 'Bitte Gesamtfehlstunden angeben (ggf. 0).' );
			}
			if ( '' === $missed_ue_raw ) {
				$this->add_error( 'missed_ue', 'Bitte unentschuldigte Stunden angeben (ggf. 0).' );
			}

			// Plausibilität: Unentschuldigt <= Gesamt
			if ( is_numeric( $missed_hours_raw ) && is_numeric( $missed_ue_raw ) ) {
				if ( $missed_ue > $missed_hours ) {
					$this->add_error( 'missed_ue', 'Unentschuldigte Stunden können nicht höher sein als Gesamtfehlstunden.' );
				}
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
		];

		return empty( $this->errors );
	}
}