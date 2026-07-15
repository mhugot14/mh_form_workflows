<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 2 der Absentismus-Eskalation: 2. Pädagogisches Gespräch mit Schüler/-in
 * (Eltern-Einladung, erweiterte Teilnehmerrunde).
 */
class Absentismus_Step_Gespraech_2_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'gespraech_2';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$einladung_datum   = $this->sanitize_text( $data['einladung_datum'] ?? '' );
		$einladung_uhrzeit = $this->sanitize_text( $data['einladung_uhrzeit'] ?? '' );
		$versand_einladung = $this->sanitize_text( $data['versand_einladung'] ?? '' );

		$datum       = $this->sanitize_text( $data['datum'] ?? '' );
		$uhrzeit_von = $this->sanitize_text( $data['uhrzeit_von'] ?? '' );
		$uhrzeit_bis = $this->sanitize_text( $data['uhrzeit_bis'] ?? '' );
		$ort         = $this->sanitize_text( $data['ort'] ?? '' );

		$abteilungsleitung    = $this->sanitize_text( $data['abteilungsleitung'] ?? '' );
		$schulsozialarbeit    = $this->sanitize_text( $data['schulsozialarbeit'] ?? '' );
		$erziehungsber_1      = $this->sanitize_text( $data['erziehungsberechtigte_1'] ?? '' );
		$erziehungsber_2      = $this->sanitize_text( $data['erziehungsberechtigte_2'] ?? '' );
		$weitere              = $this->sanitize_text( $data['weitere'] ?? '' );

		$inhalte    = sanitize_textarea_field( $data['inhalte'] ?? '' );
		$ergebnisse = sanitize_textarea_field( $data['ergebnisse'] ?? '' );

		$ankuendigung_attestpflicht = $this->sanitize_checkbox( $data, 'ankuendigung_attestpflicht' );
		$kontakt_sozialarbeit       = $this->sanitize_checkbox( $data, 'kontakt_sozialarbeit' );

		$ueberpruefung = $this->sanitize_text( $data['ueberpruefung_am'] ?? '' );

		// Einladung mit Unterschrift Klassenleitung + Erziehungsberechtigte ist laut
		// Vorlage nur beim 2. Gespräch für SCHULPFLICHTIGE SuS Pflicht; nicht mehr
		// schulpflichtige SuS werden ohne Sekretariats-Einladung eingeladen.
		$is_schulpflichtig = isset( $data['is_schulpflichtig'] ) && '1' === $data['is_schulpflichtig'];
		if ( $is_schulpflichtig && ( empty( $einladung_datum ) || empty( $einladung_uhrzeit ) ) ) {
			$this->add_error( 'einladung_datum', 'Datum/Uhrzeit des 2. Pädagogischen Gesprächs fehlt.' );
		}
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
			'einladung_datum'            => $einladung_datum,
			'einladung_uhrzeit'          => $einladung_uhrzeit,
			'versand_einladung'          => $versand_einladung,
			'datum'                      => $datum,
			'uhrzeit_von'                => $uhrzeit_von,
			'uhrzeit_bis'                => $uhrzeit_bis,
			'ort'                        => $ort,
			'abteilungsleitung'          => $abteilungsleitung,
			'schulsozialarbeit'          => $schulsozialarbeit,
			'erziehungsberechtigte_1'    => $erziehungsber_1,
			'erziehungsberechtigte_2'    => $erziehungsber_2,
			'weitere'                    => $weitere,
			'fehlstunden_gesamt'         => $fehlstunden_gesamt,
			'fehlstunden_unentschuldigt' => $fehlstunden_ue,
			'inhalte'                    => $inhalte,
			'ergebnisse'                 => $ergebnisse,
			'ankuendigung_attestpflicht' => $ankuendigung_attestpflicht,
			'kontakt_sozialarbeit'       => $kontakt_sozialarbeit,
			'ueberpruefung_am'           => $ueberpruefung,
		];

		return empty( $this->errors );
	}
}
