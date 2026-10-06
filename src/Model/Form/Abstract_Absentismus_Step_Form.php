<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Gemeinsame Validierungs-Helfer für die 8 Absentismus-Schritt-Formulare
 * (Fehlzeiten-Eskalationsverfahren). Die Fall-Stammdaten (Schüler, Klasse,
 * Klassenleitung) werden hier bewusst NICHT validiert — die liegen auf
 * Fall-Ebene und werden nur einmal beim Fall-Eröffnen erfasst.
 */
abstract class Abstract_Absentismus_Step_Form extends Abstract_Form {

	/**
	 * Prüft ein Fehlstunden-Paar (gesamt/unentschuldigt). Beide Pflicht (auch '0'
	 * erlaubt), unentschuldigt darf nicht höher sein als gesamt.
	 *
	 * @return array{0:int,1:int} [gesamt, unentschuldigt]
	 */
	protected function validate_fehlstunden_pair( array $data, string $total_field, string $ue_field ): array {
		$total_raw = trim( (string) ( $data[ $total_field ] ?? '' ) );
		$ue_raw    = trim( (string) ( $data[ $ue_field ] ?? '' ) );

		if ( '' === $total_raw ) {
			$this->add_error( $total_field, 'Bitte Anzahl der Fehlstunden gesamt angeben (ggf. 0).' );
		}
		if ( '' === $ue_raw ) {
			$this->add_error( $ue_field, 'Bitte Anzahl der unentschuldigten Fehlstunden angeben (ggf. 0).' );
		}

		$total = (int) $total_raw;
		$ue    = (int) $ue_raw;

		if ( is_numeric( $total_raw ) && is_numeric( $ue_raw ) && $ue > $total ) {
			$this->add_error( $ue_field, 'Unentschuldigte Fehlstunden können nicht höher sein als die Gesamtzahl.' );
		}

		return [ $total, $ue ];
	}

	/**
	 * Pädagogische Gespräche (gespraech_1/_2/_weiteres) dürfen statt der
	 * vollständigen Protokollierung als "bereits extern dokumentiert" (WebUntis
	 * oder handschriftlich) erfasst werden. Dann ist nur das Gesprächsdatum
	 * Pflicht; alle übrigen Felder werden weder validiert noch gespeichert.
	 *
	 * @return array{dokumentation:string,datum:string}|null Fertige Schritt-Daten
	 *         bei externer Dokumentation, null bei Dokumentation im Formular.
	 */
	protected function validate_extern_dokumentation( array $data ): ?array {
		$dokumentation = $this->sanitize_text( $data['dokumentation'] ?? 'formular' );
		if ( ! in_array( $dokumentation, [ 'webuntis', 'handschriftlich' ], true ) ) {
			return null;
		}

		$datum = $this->sanitize_text( $data['datum'] ?? '' );
		if ( empty( $datum ) ) {
			$this->add_error( 'datum', 'Datum des Gesprächs fehlt.' );
		}

		return [
			'dokumentation' => $dokumentation,
			'datum'         => $datum,
		];
	}

	protected function validate_paraphe( array $data, string $field, string $label ): string {
		$value = $this->sanitize_text( $data[ $field ] ?? '' );
		if ( empty( $value ) ) {
			$this->add_error( $field, sprintf( '%s fehlt.', $label ) );
		}
		return $value;
	}

	protected function sanitize_checkbox( array $data, string $field ): string {
		return isset( $data[ $field ] ) ? '1' : '0';
	}

	/**
	 * @param string[] $fields
	 * @return array<string,string> Feld => '1'/'0'
	 */
	protected function sanitize_weekday_checkboxes( array $data, array $fields ): array {
		$result = [];
		foreach ( $fields as $field ) {
			$result[ $field ] = $this->sanitize_checkbox( $data, $field );
		}
		return $result;
	}

	/**
	 * @param string[] $fields
	 * @return array<string,string> Feld => '1'/'0'
	 */
	protected function sanitize_anlage_checkboxes( array $data, array $fields ): array {
		return $this->sanitize_weekday_checkboxes( $data, $fields );
	}
}
