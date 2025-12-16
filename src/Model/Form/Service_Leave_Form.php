<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

class Service_Leave_Form extends Abstract_Form {

	public function get_slug(): string {
		return 'service_leave_v1';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		// 1. Basisdaten
		$lastname  = $this->sanitize_text( $data['lastname'] ?? '' );
		$firstname = $this->sanitize_text( $data['firstname'] ?? '' );
		
		$date_start = $this->sanitize_text( $data['date_start'] ?? '' );
		$date_end   = $this->sanitize_text( $data['date_end'] ?? '' ); // Optional, wenn nur 1 Tag
		$time_start = $this->sanitize_text( $data['time_start'] ?? '' );
		$time_end   = $this->sanitize_text( $data['time_end'] ?? '' );
		
		// 2. Kategorie & Grund (Die große Tabelle oben)
		// Wir erwarten, dass der Radio-Button/Checkbox einen value wie 'fortbildung_br' sendet.
		// category: 'dienst', 'unterricht', 'unfall', 'sonder'
		$category = $this->sanitize_text( $data['category'] ?? '' );
		$reason_key = $this->sanitize_text( $data['reason_key'] ?? '' ); // Der spezifische Grund
		$reason_text = sanitize_textarea_field( $data['reason_text'] ?? '' ); // Freitextfeld

		// 3. Details
		$colleagues = $this->sanitize_text( $data['colleagues'] ?? '' );
		$collision  = $this->sanitize_text( $data['collision'] ?? '' );

		// 4. Vertretungsplan (Tabelle Seite 2)
		// Wir erwarten Arrays: sub_date[], sub_group[], sub_hour[], sub_info[]
		$sub_rows = [];
		if ( isset($data['sub_date']) && is_array($data['sub_date']) ) {
			for($i = 0; $i < count($data['sub_date']); $i++) {
				// Nur Zeilen speichern, wo Datum oder Stunde ausgefüllt ist
				if( !empty($data['sub_date'][$i]) || !empty($data['sub_hour'][$i]) ) {
					$sub_rows[] = [
						'date'  => $this->sanitize_text($data['sub_date'][$i]),
						'group' => $this->sanitize_text($data['sub_group'][$i] ?? ''),
						'hour'  => $this->sanitize_text($data['sub_hour'][$i] ?? ''),
						'info'  => $this->sanitize_text($data['sub_info'][$i] ?? ''),
					];
				}
			}
		}

		// --- VALIDIERUNG ---
		if ( empty( $lastname ) ) $this->add_error( 'lastname', 'Nachname fehlt.' );
		if ( empty( $firstname ) ) $this->add_error( 'firstname', 'Vorname fehlt.' );
		
		if ( empty( $date_start ) ) $this->add_error( 'date_start', 'Startdatum fehlt.' );
		
		if ( empty( $category ) || empty($reason_key) ) {
			$this->add_error( 'category', 'Bitte einen Grund für den Antrag auswählen (Tabelle oben).' );
		}
		
		// Bei "Sonstige" muss Text da sein? (Beispiel)
		if ( $reason_key === 'sonstige' && empty($reason_text) ) {
			$this->add_error( 'reason_text', 'Bitte Begründung angeben.' );
		}

		// --- DATEN SPEICHERN ---
		$this->data = [
			'lastname' => $lastname,
			'firstname' => $firstname,
			'date_start' => $date_start,
			'date_end' => $date_end,
			'time_start' => $time_start,
			'time_end' => $time_end,
			
			'category' => $category,
			'reason_key' => $reason_key,
			'reason_text' => $reason_text,
			
			'colleagues' => $colleagues,
			'collision' => $collision,
			
			'sub_rows' => $sub_rows, // Das Array für Seite 2
		];

		return empty( $this->errors );
	}
}