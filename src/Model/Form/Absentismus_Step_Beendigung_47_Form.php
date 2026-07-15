<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 8 (letzter Schritt) der Absentismus-Eskalation: Beendigung
 * Schulverhältnis nach § 47 Abs. 1 Nr. 8 SchulG.
 */
class Absentismus_Step_Beendigung_47_Form extends Abstract_Absentismus_Step_Form {

	public function get_slug(): string {
		return 'beendigung_47';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$tage_raw = trim( (string) ( $data['tage_am_stueck'] ?? '' ) );
		if ( '' === $tage_raw ) {
			$this->add_error( 'tage_am_stueck', 'Anzahl der unentschuldigten Tage am Stück fehlt.' );
		}
		$tage_am_stueck = (int) $tage_raw;

		$erinnerungsschreiben = $this->sanitize_checkbox( $data, 'erinnerungsschreiben' );
		$erinnerung_tage_seitdem = $this->sanitize_text( $data['erinnerung_tage_seitdem'] ?? '' );
		$erinnerung_versand      = $this->sanitize_text( $data['erinnerung_versand'] ?? '' );

		if ( '1' === $erinnerungsschreiben ) {
			if ( empty( $erinnerung_tage_seitdem ) ) {
				$this->add_error( 'erinnerung_tage_seitdem', 'Bitte angeben, seit wann am Stück unentschuldigt gefehlt wird.' );
			}
			if ( empty( $erinnerung_versand ) ) {
				$this->add_error( 'erinnerung_versand', 'Versanddatum des Erinnerungsschreibens fehlt.' );
			}
		}

		$ausschulungsschreiben = $this->sanitize_checkbox( $data, 'ausschulungsschreiben' );
		$ausschulung_versand   = $this->sanitize_text( $data['ausschulung_versand'] ?? '' );
		$ausschulung_grund     = sanitize_textarea_field( $data['ausschulung_grund'] ?? '' );

		if ( '1' === $ausschulungsschreiben && empty( $ausschulung_versand ) && empty( $ausschulung_grund ) ) {
			$this->add_error( 'ausschulung_versand', 'Bitte entweder das Versanddatum der Ausschulung oder einen Grund angeben, warum sie nicht erfolgt ist.' );
		}

		$this->data = [
			'tage_am_stueck'           => $tage_am_stueck,
			'erinnerungsschreiben'     => $erinnerungsschreiben,
			'erinnerung_tage_seitdem'  => $erinnerung_tage_seitdem,
			'erinnerung_versand'       => $erinnerung_versand,
			'ausschulungsschreiben'    => $ausschulungsschreiben,
			'ausschulung_versand'      => $ausschulung_versand,
			'ausschulung_grund'        => $ausschulung_grund,
		];

		return empty( $this->errors );
	}
}
