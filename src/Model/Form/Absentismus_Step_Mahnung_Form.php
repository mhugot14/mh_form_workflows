<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 5 der Absentismus-Eskalation: Schriftliche Mahnung (Verweis) /
 * Aufforderung des Schulbesuchs.
 */
class Absentismus_Step_Mahnung_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'mahnung';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		[ $fehlstunden_gesamt, $fehlstunden_ue ] = $this->validate_fehlstunden_pair( $data, 'fehlstunden_gesamt', 'fehlstunden_unentschuldigt' );

		$paraphe_al = $this->validate_paraphe( $data, 'paraphe_al', 'Paraphe Abteilungsleitung' );

		$anlage_webuntis  = $this->sanitize_checkbox( $data, 'anlage_webuntis' );
		$anlage_protokoll = $this->sanitize_checkbox( $data, 'anlage_protokoll' );
		$versand_datum    = $this->sanitize_text( $data['versand_datum'] ?? '' );

		$this->data = [
			'fehlstunden_gesamt'         => $fehlstunden_gesamt,
			'fehlstunden_unentschuldigt' => $fehlstunden_ue,
			'paraphe_al'                 => $paraphe_al,
			'anlage_webuntis'            => $anlage_webuntis,
			'anlage_protokoll'           => $anlage_protokoll,
			'versand_datum'              => $versand_datum,
		];

		return empty( $this->errors );
	}
}
