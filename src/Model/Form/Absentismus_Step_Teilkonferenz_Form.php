<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Schritt 7 der Absentismus-Eskalation: Teilkonferenz.
 */
class Absentismus_Step_Teilkonferenz_Form extends Abstract_Absentismus_Step_Form {

	private const MAX_TAGE_BEENDIGUNG = 30;
	private const MIN_FEHLSTUNDEN_BEENDIGUNG = 20;

	public function get_slug(): string {
		return 'teilkonferenz';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$trigger = $this->sanitize_text( $data['trigger'] ?? '' );
		if ( ! in_array( $trigger, [ 'beendigung_53', 'hohe_fehlstunden' ], true ) ) {
			$this->add_error( 'trigger', 'Bitte einen Grund für die Teilkonferenz auswählen.' );
		}

		$zeitraum_von = $this->sanitize_text( $data['zeitraum_von'] ?? '' );
		$zeitraum_bis = $this->sanitize_text( $data['zeitraum_bis'] ?? '' );
		if ( empty( $zeitraum_von ) || empty( $zeitraum_bis ) ) {
			$this->add_error( 'zeitraum_von', 'Zeitraum (von/bis) fehlt.' );
		}

		$fehlstunden_raw = trim( (string) ( $data['fehlstunden_unentschuldigt'] ?? '' ) );
		if ( '' === $fehlstunden_raw ) {
			$this->add_error( 'fehlstunden_unentschuldigt', 'Anzahl der unentschuldigten Fehlstunden fehlt.' );
		}
		$fehlstunden = (int) $fehlstunden_raw;

		if ( 'beendigung_53' === $trigger && ! empty( $zeitraum_von ) && ! empty( $zeitraum_bis ) ) {
			$tage = (int) round( ( strtotime( $zeitraum_bis ) - strtotime( $zeitraum_von ) ) / DAY_IN_SECONDS );
			if ( $tage > self::MAX_TAGE_BEENDIGUNG ) {
				$this->add_error( 'zeitraum_bis', sprintf( 'Der Zeitraum darf maximal %d Tage umfassen.', self::MAX_TAGE_BEENDIGUNG ) );
			}
			if ( is_numeric( $fehlstunden_raw ) && $fehlstunden < self::MIN_FEHLSTUNDEN_BEENDIGUNG ) {
				$this->add_error( 'fehlstunden_unentschuldigt', sprintf( 'Für diesen Grund sind mindestens %d unentschuldigte Fehlstunden erforderlich.', self::MIN_FEHLSTUNDEN_BEENDIGUNG ) );
			}
		}

		// Beschluss ist bewusst optional: er steht oft erst nach der Konferenz fest —
		// der Entwurf wird beim Verweis angelegt, der Beschluss wird nachgetragen,
		// bevor der Schritt festgeschrieben wird. Führt NICHT automatisch zum
		// Schließen des Falls (das entscheidet die Klassenleitung manuell).
		$beschluss = $this->sanitize_text( $data['beschluss'] ?? '' );
		if ( ! in_array( $beschluss, [ 'androhung_entlassung', 'entlassung', '' ], true ) ) {
			$beschluss = '';
		}

		$this->data = [
			'trigger'                    => $trigger,
			'zeitraum_von'                => $zeitraum_von,
			'zeitraum_bis'                => $zeitraum_bis,
			'fehlstunden_unentschuldigt' => $fehlstunden,
			'beschluss'                  => $beschluss,
		];

		return empty( $this->errors );
	}
}
