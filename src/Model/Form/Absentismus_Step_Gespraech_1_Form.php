<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 1 der Absentismus-Eskalation: 1. Pädagogisches Gespräch mit Schüler/-in.
 */
class Absentismus_Step_Gespraech_1_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'gespraech_1';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$datum         = $this->sanitize_text( $data['datum'] ?? '' );
		$uhrzeit_von   = $this->sanitize_text( $data['uhrzeit_von'] ?? '' );
		$uhrzeit_bis   = $this->sanitize_text( $data['uhrzeit_bis'] ?? '' );
		$ort           = $this->sanitize_text( $data['ort'] ?? '' );
		$sozialarbeit  = $this->sanitize_text( $data['schulsozialarbeit'] ?? '' );
		$weitere       = $this->sanitize_text( $data['weitere'] ?? '' );
		$erziehungsber = sanitize_textarea_field( $data['erziehungsberechtigte'] ?? '' );
		$inhalte       = sanitize_textarea_field( $data['inhalte'] ?? '' );
		$ergebnisse    = sanitize_textarea_field( $data['ergebnisse'] ?? '' );
		$ueberpruefung = $this->sanitize_text( $data['ueberpruefung_am'] ?? '' );

		if ( empty( $datum ) ) {
			$this->add_error( 'datum', 'Datum des Gesprächs fehlt.' );
		}
		if ( empty( $uhrzeit_von ) || empty( $uhrzeit_bis ) ) {
			$this->add_error( 'uhrzeit_von', 'Uhrzeit (von/bis) fehlt.' );
		}
		if ( ! in_array( $ort, [ 'schule', 'telefonat' ], true ) ) {
			$this->add_error( 'ort', 'Bitte Ort des Gesprächs auswählen.' );
		}
		if ( empty( $inhalte ) ) {
			$this->add_error( 'inhalte', 'Bitte Inhalte des Gesprächs eintragen.' );
		}
		if ( empty( $ergebnisse ) ) {
			$this->add_error( 'ergebnisse', 'Bitte Ergebnisse/Vereinbarungen eintragen.' );
		}
		if ( empty( $ueberpruefung ) ) {
			$this->add_error( 'ueberpruefung_am', 'Datum zur Überprüfung der Vereinbarungen fehlt.' );
		} elseif ( strtotime( $ueberpruefung ) < strtotime( 'today' ) ) {
			$this->add_error( 'ueberpruefung_am', 'Das Überprüfungsdatum sollte in der Zukunft liegen.' );
		}

		[ $fehlstunden_gesamt, $fehlstunden_ue ] = $this->validate_fehlstunden_pair( $data, 'fehlstunden_gesamt', 'fehlstunden_unentschuldigt' );

		$this->data = [
			'datum'                  => $datum,
			'uhrzeit_von'            => $uhrzeit_von,
			'uhrzeit_bis'            => $uhrzeit_bis,
			'ort'                    => $ort,
			'schulsozialarbeit'      => $sozialarbeit,
			'weitere'                => $weitere,
			'erziehungsberechtigte'  => $erziehungsber,
			'fehlstunden_gesamt'     => $fehlstunden_gesamt,
			'fehlstunden_unentschuldigt' => $fehlstunden_ue,
			'inhalte'                => $inhalte,
			'ergebnisse'             => $ergebnisse,
			'ueberpruefung_am'       => $ueberpruefung,
		];

		return empty( $this->errors );
	}
}
