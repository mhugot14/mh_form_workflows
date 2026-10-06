<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

use DateTimeImmutable;
use Mh\FormWorkflows\Service\Nachschreib_Slot_Provider_Interface;
use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;

/**
 * Class Nachschreib_Anmeldung_Form
 *
 * Anmeldung von Schüler*innen zu einem Nachschreibtermin - ein Formular für alle drei
 * Terminarten (kurz, lang, samstag). Die Art steuert, welche Termine und Arbeitszeiten
 * gültig sind; die Regeln dazu stehen im Nachschreib_Termin_Katalog.
 *
 * Fehlerschlüssel der Zeilen lauten "rows.<i>.<feld>", damit das Template genau das
 * betroffene Feld markieren kann.
 */
class Nachschreib_Anmeldung_Form extends Abstract_Form {

	/** Obergrenze, damit ein manipuliertes Formular keine Riesen-PDFs erzeugt. */
	public const MAX_ROWS = 20;

	/**
	 * @param string[]    $known_teachers Gültige Lehrerkürzel; leer = keine Prüfung (Tabelle fehlt).
	 * @param array|null  $previous       Gespeicherter Stand beim Bearbeiten - ist der Termin
	 *                                    unverändert, gilt die Abgabefrist nicht erneut, damit
	 *                                    sich ein Tippfehler auch nach Fristende noch korrigieren lässt.
	 * @param int         $entry_id       ID der bearbeiteten Anmeldung - ihre Schüler*innen zählen
	 *                                    bei der Belegung nicht doppelt.
	 */
	public function __construct(
		private Nachschreib_Termin_Katalog $katalog,
		private Nachschreib_Slot_Provider_Interface $slots,
		private DateTimeImmutable $now,
		private array $known_teachers = [],
		private ?array $previous = null,
		private int $entry_id = 0
	) {}

	public function get_slug(): string {
		return Nachschreib_Termin_Katalog::FORM_TYPE;
	}

	public function validate( array $data ): bool {
		$this->errors = [];
		$this->data   = [];

		$typ   = $this->sanitize_text( $data['termin_typ'] ?? '' );
		$datum = $this->sanitize_text( $data['termin_datum'] ?? '' );

		// --- Termin ---
		$slot = null;
		if ( ! $this->katalog->exists( $typ ) ) {
			$this->add_error( 'termin_typ', 'Bitte die Art des Nachschreibtermins wählen.' );
			$typ = '';
		} elseif ( '' === $datum ) {
			$this->add_error( 'termin_datum', 'Bitte einen Termin auswählen.' );
		} else {
			$slot = $this->slots->find_slot( $typ, $datum, $this->entry_id );
			if ( null === $slot ) {
				$this->add_error( 'termin_datum', 'An diesem Tag findet kein Termin der Art „' . $this->katalog->get( $typ )['label'] . '“ statt (oder er wurde deaktiviert). Bitte einen der angebotenen Termine wählen.' );
			} elseif ( $this->slots->is_past_deadline( $slot, $this->now ) && ! $this->is_unchanged_termin( $typ, $datum ) ) {
				$this->add_error( 'termin_datum', 'Die Abgabefrist für diesen Termin ist abgelaufen (' . $slot['frist_label'] . '). Bitte den nächsten Termin wählen.' );
			}
		}

		// --- Schüler*innen ---
		$rows      = [];
		$raw_rows  = is_array( $data['rows'] ?? null ) ? array_values( $data['rows'] ) : [];
		$def       = '' !== $typ ? $this->katalog->get( $typ ) : null;
		$seen      = [];

		foreach ( array_slice( $raw_rows, 0, self::MAX_ROWS ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$row = $this->sanitize_row( $raw );

			// Komplett leere Zeilen (z. B. eine angefangene, dann vergessene) still verwerfen.
			if ( '' === $row['lastname'] && '' === $row['firstname'] && '' === $row['subject'] && '' === $row['class_name'] ) {
				continue;
			}

			$i = count( $rows );
			$p = 'rows.' . $i . '.';

			if ( '' === $row['class_name'] ) {
				$this->add_error( $p . 'class', 'Zeile ' . ( $i + 1 ) . ': Klasse fehlt.' );
			}
			if ( '' === $row['lastname'] || '' === $row['firstname'] ) {
				$this->add_error( $p . 'student', 'Zeile ' . ( $i + 1 ) . ': Bitte Schüler*in auswählen bzw. Vor- und Nachnamen eintragen.' );
			}
			if ( '' === $row['subject'] ) {
				$this->add_error( $p . 'subject', 'Zeile ' . ( $i + 1 ) . ': Fach fehlt.' );
			}
			if ( '' === $row['teacher'] ) {
				$this->add_error( $p . 'teacher', 'Zeile ' . ( $i + 1 ) . ': Kürzel der Fachlehrkraft fehlt.' );
			} elseif ( ! empty( $this->known_teachers ) && ! in_array( $row['teacher'], $this->known_teachers, true ) ) {
				$this->add_error( $p . 'teacher', 'Zeile ' . ( $i + 1 ) . ': Das Kürzel „' . $row['teacher'] . '“ ist nicht bekannt.' );
			}
			if ( $row['duration'] <= 0 ) {
				$this->add_error( $p . 'duration', 'Zeile ' . ( $i + 1 ) . ': Arbeitszeit fehlt.' );
			} elseif ( null !== $def && ( $row['duration'] < $def['dauer_min'] || $row['duration'] > $def['dauer_max'] ) ) {
				$this->add_error( $p . 'duration', 'Zeile ' . ( $i + 1 ) . ': ' . $row['duration'] . ' Minuten passen nicht zu „' . $def['label'] . '“ (erlaubt: ' . $def['dauer_min'] . '–' . $def['dauer_max'] . ' Min.).' );
			}
			if ( '' === $row['aids'] ) {
				$this->add_error( $p . 'aids', 'Zeile ' . ( $i + 1 ) . ': Hilfsmittel fehlen – ggf. „keine“ eintragen.' );
			}

			// Dieselbe Person zweimal im selben Fach ist fast immer ein Versehen.
			$key = mb_strtolower( $row['lastname'] . '|' . $row['firstname'] . '|' . $row['subject'] );
			if ( '' !== $row['lastname'] && isset( $seen[ $key ] ) ) {
				$this->add_error( $p . 'student', 'Zeile ' . ( $i + 1 ) . ': ' . $row['firstname'] . ' ' . $row['lastname'] . ' ist für ' . $row['subject'] . ' bereits in Zeile ' . ( $seen[ $key ] + 1 ) . ' eingetragen.' );
			}
			$seen[ $key ] = $i;

			$rows[] = $row;
		}

		if ( empty( $rows ) ) {
			$this->add_error( 'rows', 'Bitte mindestens eine Schülerin bzw. einen Schüler eintragen.' );
		}

		// Kontingent: belegt zählt ohne diese Anmeldung (exclude über entry_id), deshalb
		// darf eine bestehende Anmeldung auf einem vollen Termin unverändert bleiben.
		if ( null !== $slot && count( $rows ) > $slot['rest'] && ! isset( $this->errors['termin_datum'] ) ) {
			$this->add_error( 'termin_datum', 0 === $slot['rest']
				? 'Der Termin am ' . $slot['label'] . ' ist ausgebucht. Bitte einen anderen Termin wählen.'
				: 'Für den Termin am ' . $slot['label'] . ( 1 === $slot['rest'] ? ' ist nur noch 1 Platz frei' : ' sind nur noch ' . $slot['rest'] . ' Plätze frei' ) . ', angemeldet werden sollen ' . count( $rows ) . ' Schüler*innen.' );
		}

		// --- Bestätigungen (Pflicht laut Vorlage) ---
		$confirm_berechtigung = ( '1' === ( $data['confirm_berechtigung'] ?? '' ) ) ? '1' : '0';
		$confirm_info         = ( '1' === ( $data['confirm_info'] ?? '' ) ) ? '1' : '0';
		if ( '1' !== $confirm_berechtigung ) {
			$this->add_error( 'confirm_berechtigung', 'Bitte bestätigen, dass die Berechtigung zur Teilnahme geprüft wurde.' );
		}
		if ( '1' !== $confirm_info ) {
			$this->add_error( 'confirm_info', 'Bitte bestätigen, dass die Schüler*innen informiert wurden.' );
		}

		$this->data = [
			'schema'               => 1,
			'termin_typ'           => $typ,
			'termin_datum'         => $datum,
			// Momentaufnahme des Termins: Ändert sich später der Katalog (Raum, Uhrzeit),
			// zeigt ein erneut erzeugtes PDF trotzdem, was bei der Anmeldung galt.
			'termin'               => $slot,
			'rows'                 => $rows,
			'remark'               => sanitize_textarea_field( (string) ( $data['remark'] ?? '' ) ),
			'confirm_berechtigung' => $confirm_berechtigung,
			'confirm_info'         => $confirm_info,
		];

		return empty( $this->errors );
	}

	/**
	 * @return array{class_wu_id:int,class_name:string,student_wu_id:int,lastname:string,firstname:string,subject:string,teacher:string,duration:int,aids:string}
	 */
	private function sanitize_row( array $raw ): array {
		$duration = $this->sanitize_text( $raw['duration'] ?? '' );
		// „individuell …“ im Formular: die Minuten stehen im Zusatzfeld.
		if ( 'custom' === $duration ) {
			$duration = trim( $this->sanitize_text( $raw['duration_custom'] ?? '' ) );
		}

		return [
			'class_wu_id'   => max( 0, (int) ( $raw['class_wu_id'] ?? 0 ) ),
			'class_name'    => trim( $this->sanitize_text( $raw['class_name'] ?? '' ) ),
			// 0 = von Hand eingetragen (Schüler*in nicht in der WebUntis-Liste).
			'student_wu_id' => max( 0, (int) ( $raw['student_wu_id'] ?? 0 ) ),
			'lastname'      => trim( $this->sanitize_text( $raw['lastname'] ?? '' ) ),
			'firstname'     => trim( $this->sanitize_text( $raw['firstname'] ?? '' ) ),
			'subject'       => trim( $this->sanitize_text( $raw['subject'] ?? '' ) ),
			'teacher'       => mb_strtoupper( trim( $this->sanitize_text( $raw['teacher'] ?? '' ) ) ),
			'duration'      => ctype_digit( $duration ) ? (int) $duration : 0,
			'aids'          => trim( $this->sanitize_text( $raw['aids'] ?? '' ) ),
		];
	}

	private function is_unchanged_termin( string $typ, string $datum ): bool {
		return null !== $this->previous
			&& ( $this->previous['termin_typ'] ?? '' ) === $typ
			&& ( $this->previous['termin_datum'] ?? '' ) === $datum;
	}
}
