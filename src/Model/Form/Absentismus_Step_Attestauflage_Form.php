<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 4 der Absentismus-Eskalation: Attestauflage.
 */
class Absentismus_Step_Attestauflage_Form extends Abstract_Absentismus_Step_Form {

	private const GRUND_FELDER = [
		'grund_haeufige_verspaetungen',
		'grund_vorzeitiges_beenden',
		'grund_nicht_zusammenhaengende_fehltage',
		'grund_bestimmte_wochentage',
		'grund_sonstige',
	];

	private const WOCHENTAGE = [ 'weekday_mo', 'weekday_di', 'weekday_mi', 'weekday_do', 'weekday_fr' ];

	public function get_slug(): string {
		return 'attestauflage';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		[ $fehlstunden_gesamt, $fehlstunden_ue ] = $this->validate_fehlstunden_pair( $data, 'fehlstunden_gesamt', 'fehlstunden_unentschuldigt' );

		$ankuendigung_datum = $this->sanitize_text( $data['ankuendigung_datum'] ?? '' );
		if ( empty( $ankuendigung_datum ) ) {
			$this->add_error( 'ankuendigung_datum', 'Datum der Ankündigung der Attestauflage fehlt.' );
		}

		$paraphe_al = $this->validate_paraphe( $data, 'paraphe_al', 'Paraphe Abteilungsleitung' );

		$gruende = $this->sanitize_anlage_checkboxes( $data, self::GRUND_FELDER );
		if ( ! in_array( '1', $gruende, true ) ) {
			$this->add_error( 'grund_haeufige_verspaetungen', 'Bitte mindestens einen Grund auswählen.' );
		}

		$wochentage = $this->sanitize_weekday_checkboxes( $data, self::WOCHENTAGE );
		if ( '1' === $gruende['grund_bestimmte_wochentage'] && ! in_array( '1', $wochentage, true ) ) {
			$this->add_error( 'weekday_mo', 'Bitte mindestens einen Wochentag auswählen.' );
		}

		$grund_sonstige_text = sanitize_textarea_field( $data['grund_sonstige_text'] ?? '' );
		if ( '1' === $gruende['grund_sonstige'] && empty( $grund_sonstige_text ) ) {
			$this->add_error( 'grund_sonstige_text', 'Bitte den sonstigen Grund näher beschreiben.' );
		}

		$anlage_webuntis  = $this->sanitize_checkbox( $data, 'anlage_webuntis' );
		$anlage_protokoll = $this->sanitize_checkbox( $data, 'anlage_protokoll' );
		$versand_datum    = $this->sanitize_text( $data['versand_datum'] ?? '' );

		$this->data = array_merge( [
			'fehlstunden_gesamt'         => $fehlstunden_gesamt,
			'fehlstunden_unentschuldigt' => $fehlstunden_ue,
			'ankuendigung_datum'         => $ankuendigung_datum,
			'paraphe_al'                 => $paraphe_al,
			'grund_sonstige_text'        => $grund_sonstige_text,
			'anlage_webuntis'            => $anlage_webuntis,
			'anlage_protokoll'           => $anlage_protokoll,
			'versand_datum'              => $versand_datum,
		], $gruende, $wochentage );

		return empty( $this->errors );
	}
}
