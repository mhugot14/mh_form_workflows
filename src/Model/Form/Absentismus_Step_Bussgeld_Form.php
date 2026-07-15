<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 6 der Absentismus-Eskalation: Einleitung Bußgeldverfahren / Anhörung.
 */
class Absentismus_Step_Bussgeld_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'bussgeld';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$anlage_webuntis         = $this->sanitize_checkbox( $data, 'anlage_webuntis' );
		$versand_anhoerungsbogen = $this->sanitize_text( $data['versand_anhoerungsbogen'] ?? '' );

		$mail_bezirksregierung       = $this->sanitize_checkbox( $data, 'mail_bezirksregierung' );
		$mail_bezirksregierung_datum = $this->sanitize_text( $data['mail_bezirksregierung_datum'] ?? '' );

		if ( '1' === $mail_bezirksregierung && empty( $mail_bezirksregierung_datum ) ) {
			$this->add_error( 'mail_bezirksregierung_datum', 'Bitte Datum der Mail an die Bezirksregierung angeben.' );
		}

		$this->data = [
			'anlage_webuntis'              => $anlage_webuntis,
			'versand_anhoerungsbogen'      => $versand_anhoerungsbogen,
			'mail_bezirksregierung'        => $mail_bezirksregierung,
			'mail_bezirksregierung_datum'  => $mail_bezirksregierung_datum,
		];

		return empty( $this->errors );
	}
}
