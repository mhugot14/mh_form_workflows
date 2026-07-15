<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 3 der Absentismus-Eskalation: Zuführung durch das Ordnungsamt
 * (nur für schulpflichtige Schüler:innen).
 */
class Absentismus_Step_Ordnungsamt_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'ordnungsamt';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		// Fall-Stammdatum, wird als Hidden-Field mitgesendet und hier zusätzlich
		// geprüft, damit das Model unabhängig vom Controller autark bleibt.
		$is_schulpflichtig = isset( $data['is_schulpflichtig'] ) && '1' === $data['is_schulpflichtig'];
		if ( ! $is_schulpflichtig ) {
			$this->add_error( 'is_schulpflichtig', 'Dieser Schritt gilt nur für schulpflichtige Schüler:innen.' );
		}

		$bezirkssozialarbeiterin = $this->validate_paraphe( $data, 'bezirkssozialarbeiterin', 'Bezirkssozialarbeiterin' );
		$telefon                 = $this->validate_paraphe( $data, 'telefon', 'Telefonnummer' );

		$zufuehrung_geplant_am = $this->sanitize_text( $data['zufuehrung_geplant_am'] ?? '' );
		if ( empty( $zufuehrung_geplant_am ) ) {
			$this->add_error( 'zufuehrung_geplant_am', 'Geplantes Zuführungsdatum fehlt.' );
		}

		$zufuehrung_erfolgreich = $this->sanitize_text( $data['zufuehrung_erfolgreich'] ?? '' );
		if ( ! in_array( $zufuehrung_erfolgreich, [ 'ja', 'nein', '' ], true ) ) {
			$zufuehrung_erfolgreich = '';
		}

		$zufuehrung_grund = sanitize_textarea_field( $data['zufuehrung_grund'] ?? '' );
		if ( 'nein' === $zufuehrung_erfolgreich && empty( $zufuehrung_grund ) ) {
			$this->add_error( 'zufuehrung_grund', 'Bitte Grund angeben, warum die Zuführung nicht erfolgreich war.' );
		}

		$this->data = [
			'is_schulpflichtig'       => $is_schulpflichtig,
			'bezirkssozialarbeiterin' => $bezirkssozialarbeiterin,
			'telefon'                 => $telefon,
			'zufuehrung_geplant_am'   => $zufuehrung_geplant_am,
			'zufuehrung_erfolgreich'  => $zufuehrung_erfolgreich,
			'zufuehrung_grund'        => $zufuehrung_grund,
		];

		return empty( $this->errors );
	}
}
