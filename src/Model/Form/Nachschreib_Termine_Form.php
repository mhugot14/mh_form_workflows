<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;
use Mh\FormWorkflows\Service\School_Date_Calculator;

/**
 * Class Nachschreib_Termine_Form
 *
 * Terminverwaltung einer Terminart: die ganze Tabelle wird auf einmal gespeichert.
 *
 * Erwartet:
 *   typ
 *   termine[<Y-m-d>][aktiv|zeit_von|zeit_bis|raum|hinweis|kontingent|loeschen]
 *   neu[datum|zeit_von|zeit_bis|raum|hinweis|kontingent]   (nur Arten ohne Vorschlagsliste)
 *
 * get_data() liefert die auszuführenden Änderungen:
 *   [ 'typ' => …, 'ops' => [ [ 'datum' => …, 'op' => 'upsert'|'delete', 'werte' => [...] ], … ] ]
 *
 * Gespeichert wird nur, was von der Vorgabe abweicht: Ein Vorschlagstag, der wieder genau
 * der Vorgabe entspricht, wird gelöscht statt als Zeile mit lauter Vorgabewerten zu bleiben.
 */
class Nachschreib_Termine_Form extends Abstract_Form {

	public function __construct(
		private Nachschreib_Termin_Katalog $katalog,
		private School_Date_Calculator $calendar
	) {}

	public function get_slug(): string {
		return 'nachschreib_termine';
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$typ = $this->sanitize_text( $data['typ'] ?? '' );
		$def = $this->katalog->get( $typ );
		if ( null === $def ) {
			$this->add_error( 'typ', 'Unbekannte Terminart.' );
			return false;
		}
		$weekly = 'weekly' === $def['slots'];
		$ops    = [];

		foreach ( (array) ( $data['termine'] ?? [] ) as $datum => $raw ) {
			$datum = (string) $datum;
			if ( ! is_array( $raw ) || ! $this->is_valid_day( $def, $datum ) ) {
				continue; // manipuliert oder inzwischen kein möglicher Tag mehr - still ignorieren
			}

			if ( ! $weekly && '1' === ( $raw['loeschen'] ?? '' ) ) {
				$ops[] = [ 'datum' => $datum, 'op' => 'delete', 'werte' => [] ];
				continue;
			}

			$werte = $this->read_values( $raw, $datum, '1' === ( $raw['aktiv'] ?? '' ) );
			if ( null === $werte ) {
				continue;
			}

			$ist_vorgabe = $weekly
				&& $werte['aktiv'] === (bool) $def['default_aktiv']
				&& '' === $werte['zeit_von'] && '' === $werte['zeit_bis']
				&& '' === $werte['raum'] && '' === $werte['hinweis']
				&& null === $werte['kontingent'];

			$ops[] = [ 'datum' => $datum, 'op' => $ist_vorgabe ? 'delete' : 'upsert', 'werte' => $werte ];
		}

		// Neuer Termin (nur für Arten ohne Vorschlagsliste).
		$neu = (array) ( $data['neu'] ?? [] );
		if ( ! $weekly && '' !== trim( (string) ( $neu['datum'] ?? '' ) ) ) {
			$datum = $this->sanitize_text( $neu['datum'] );
			if ( ! $this->is_valid_day( $def, $datum ) ) {
				$this->add_error( 'neu.datum', 'Neuer Termin: Der ' . $this->fmt( $datum ) . ' ist kein Schultag (Wochenende, Feiertag oder Ferien).' );
			} elseif ( $datum < current_time( 'Y-m-d' ) ) {
				$this->add_error( 'neu.datum', 'Neuer Termin: Das Datum liegt in der Vergangenheit.' );
			} elseif ( isset( $data['termine'][ $datum ] ) ) {
				$this->add_error( 'neu.datum', 'Neuer Termin: Am ' . $this->fmt( $datum ) . ' gibt es bereits einen Termin – bitte in der Liste bearbeiten.' );
			} else {
				$werte = $this->read_values( $neu, $datum, true, 'neu.' );
				if ( null !== $werte ) {
					$ops[] = [ 'datum' => $datum, 'op' => 'upsert', 'werte' => $werte ];
				}
			}
		}

		$this->data = [ 'typ' => $typ, 'ops' => $ops ];

		return empty( $this->errors );
	}

	/**
	 * @return array{aktiv:bool,zeit_von:string,zeit_bis:string,raum:string,hinweis:string,kontingent:?int}|null
	 */
	private function read_values( array $raw, string $datum, bool $aktiv, string $key_prefix = '' ): ?array {
		$key   = '' !== $key_prefix ? $key_prefix : 'termine.' . $datum . '.';
		$label = $this->fmt( $datum ) . ': ';

		$von  = trim( $this->sanitize_text( $raw['zeit_von'] ?? '' ) );
		$bis  = trim( $this->sanitize_text( $raw['zeit_bis'] ?? '' ) );
		$kont = trim( $this->sanitize_text( $raw['kontingent'] ?? '' ) );
		$ok   = true;

		foreach ( [ 'zeit_von' => $von, 'zeit_bis' => $bis ] as $f => $v ) {
			if ( '' !== $v && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ) {
				$this->add_error( $key . $f, $label . 'Uhrzeit „' . $v . '“ bitte als HH:MM angeben.' );
				$ok = false;
			}
		}
		if ( $ok && '' !== $von && '' !== $bis && $bis <= $von ) {
			$this->add_error( $key . 'zeit_bis', $label . 'Das Ende muss nach dem Beginn liegen.' );
			$ok = false;
		}
		if ( '' !== $kont && ( ! ctype_digit( $kont ) || (int) $kont > 999 ) ) {
			$this->add_error( $key . 'kontingent', $label . 'Plätze bitte als Zahl (0–999) angeben.' );
			$ok = false;
		}

		if ( ! $ok ) {
			return null;
		}

		return [
			'aktiv'      => $aktiv,
			'zeit_von'   => $von,
			'zeit_bis'   => $bis,
			'raum'       => mb_substr( trim( $this->sanitize_text( $raw['raum'] ?? '' ) ), 0, 100 ),
			'hinweis'    => mb_substr( trim( $this->sanitize_text( $raw['hinweis'] ?? '' ) ), 0, 255 ),
			'kontingent' => '' === $kont ? null : (int) $kont,
		];
	}

	private function is_valid_day( array $def, string $datum ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return false;
		}
		$weekday = (int) date( 'N', (int) strtotime( $datum ) );
		if ( ! in_array( $weekday, $def['weekdays'], true ) ) {
			return false;
		}
		return 6 === $weekday
			? ! $this->calendar->is_ferien_oder_feiertag_ymd( $datum )
			: $this->calendar->is_school_day_ymd( $datum );
	}

	private function fmt( string $ymd ): string {
		$ts = strtotime( $ymd );
		return false === $ts ? $ymd : date( 'd.m.Y', $ts );
	}
}
