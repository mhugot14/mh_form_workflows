<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Nachschreib_Termin_Katalog
 *
 * Die drei Nachschreibtermin-Arten der Schule mit ihren Regeln - an EINER Stelle.
 * Formular, Validierung, Terminberechnung, Terminverwaltung und PDF lesen alle hier,
 * damit eine geänderte Uhrzeit oder ein neuer Raum nur einmal nachgetragen werden muss.
 *
 * Quelle: die Word-Vorlagen "Anmeldung normaler (kurzer) Termin", "Anmeldung langer Termin"
 * und "Anmeldung Nachschreibtermin samstags" (Stand 08.10.2025 bzw. 13.03.2023).
 *
 * Die Werte hier sind die Vorgaben. Abweichungen für einzelne Tage (deaktiviert, andere
 * Uhrzeit, anderer Raum, anderes Kontingent) pflegen berechtigte Lehrkräfte im Frontend;
 * sie liegen in mh_nachschreib_termine (siehe Nachschreib_Termin_Repository).
 */
class Nachschreib_Termin_Katalog {

	/** Formulartyp in mh_form_submissions. */
	public const FORM_TYPE = 'nachschreib_anmeldung_v1';

	/** Ab so wenigen Restplätzen wird ein Termin als „fast voll“ hervorgehoben. */
	public const WENIGE_PLAETZE = 5;

	/**
	 * slots:          'weekly'     = Vorschlagsliste aus allen passenden Tagen (Wochentage unten)
	 *                 'configured' = nur die von der Terminverwaltung angelegten Tage
	 * default_aktiv:  Ist ein Vorschlag ohne Eingriff buchbar? Samstage nicht - es gibt sie
	 *                 nur „an ausgewählten Samstagen“, sie werden deshalb freigeschaltet.
	 * free_fallback:  Ist (noch) kein Termin angelegt, darf das Datum frei gewählt werden
	 * zeit_von/bis:   Vorgabe-Uhrzeit, je Tag änderbar ('' = wird je Termin festgelegt)
	 * kontingent:     Vorgabe für die Plätze je Termin, je Tag änderbar
	 * durations:      Vorschläge im Formular; erlaubt ist alles von dauer_min bis dauer_max
	 * frist_tage:     Abgabe der Unterlagen so viele Kalendertage vorher, 11:00 Uhr - fällt
	 *                 der Tag nicht auf einen Schultag, gilt der Schultag davor
	 */
	private const TYPEN = [
		'kurz' => [
			'label'         => 'Regelmäßiger Termin',
			'untertitel'    => 'Mittwoch oder Donnerstag, 9.–10. Stunde',
			'pdf_titel'     => 'Regelmäßiger Nachschreibtermin am Mittwoch oder Donnerstag 9.–10. Std.',
			'pdf_zusatz'    => 'nur für Klausuren bis 90 Minuten',
			'slots'         => 'weekly',
			'weekdays'      => [ 3, 4 ],
			'default_aktiv' => true,
			'free_fallback' => false,
			'zeit_von'      => '14:50',
			'zeit_bis'      => '16:20',
			'raum'          => 'C28',
			'kontingent'    => 20,
			'eintreffen'    => '10 Minuten vor Beginn vor dem Raum',
			'durations'     => [ 45, 60, 90 ],
			'dauer_min'     => 15,
			'dauer_max'     => 90,
			'frist_tage'    => 2,
			'postfach'      => [ 3 => 'Nachschreibtermin Mittwoch kurz', 4 => 'Nachschreibtermin Donnerstag kurz' ],
			'hinweise'      => [
				'Jeden Schul-Mittwoch und -Donnerstag in der 9.–10. Stunde.',
			],
		],
		'lang' => [
			'label'         => 'Langer Termin',
			'untertitel'    => 'Lage individuell, nur Klausuren über 90 Minuten',
			'pdf_titel'     => 'Langer Nachschreibtermin, Lage individuell',
			'pdf_zusatz'    => 'nur für Klausuren über 90 Minuten',
			'slots'         => 'configured',
			'weekdays'      => [ 1, 2, 3, 4, 5 ],
			'default_aktiv' => true,
			'free_fallback' => true,
			'zeit_von'      => '',
			'zeit_bis'      => '',
			'raum'          => 'wird je Termin vergeben (u. a. im Kerio-Kalender)',
			'kontingent'    => 20,
			'eintreffen'    => '10 Minuten vor Beginn',
			'durations'     => [ 120, 135, 150, 180, 225, 240, 270, 300 ],
			'dauer_min'     => 91,
			'dauer_max'     => 360,
			'frist_tage'    => 2,
			'postfach'      => 'Nachschreibtermin lang',
			'hinweise'      => [
				'Lange Nachschreibtermine werden in Absprache mit den Abteilungs- und Stufenleitungen nach Bedarf angeboten.',
				'Der Raum wird für jeden Termin neu vergeben und u. a. im Kerio-Kalender veröffentlicht.',
			],
		],
		'samstag' => [
			'label'         => 'Samstagstermin',
			'untertitel'    => 'ab 09:00 bis max. 12:00 Uhr, kurze und lange Klausuren',
			'pdf_titel'     => 'Nachschreibtermin am Samstag',
			'pdf_zusatz'    => 'kurze und lange Klausuren',
			'slots'         => 'weekly',
			'weekdays'      => [ 6 ],
			'default_aktiv' => false,
			'free_fallback' => false,
			'zeit_von'      => '09:00',
			'zeit_bis'      => '12:00',
			'raum'          => 'C28 und C25',
			'kontingent'    => 40,
			'eintreffen'    => '15 Minuten vor Beginn, Zugang über den Nebeneingang (rechts vor dem Haupteingang)',
			'durations'     => [ 45, 60, 90, 120, 135, 150, 180 ],
			'dauer_min'     => 15,
			'dauer_max'     => 180,
			'frist_tage'    => 2,
			'postfach'      => 'Nachschreibtermin Samstag',
			'hinweise'      => [
				'An ausgewählten Samstagen, längstens bis 12:00 Uhr.',
			],
		],
	];

	/** Gemeinsame Hinweise aller drei Vorlagen. */
	public const ALLGEMEINE_HINWEISE = [
		'Später eingehende Meldungen können nicht mehr berücksichtigt werden. Bei „Überbuchung“ muss ggf. auf die nächste Woche ausgewichen werden. Die Meldungen werden in der Reihenfolge der Eingänge bearbeitet.',
		'Meldung und Arbeitsunterlagen bitte mit Büroklammer oder Klarsichthülle zusammenfügen – keine losen Blätter.',
		'Vor der Abgabe prüfen, ob die Schüler*innen weitere Nachschreibverpflichtungen haben, und auf die Ausweispflicht am Nachschreibtermin hinweisen.',
		'Die Schüler*innen müssen durch die Fachlehrkraft rechtzeitig über Termin und Raum informiert werden.',
	];

	/** Postfach-Standort laut Vorlage. */
	public const POSTFACH_ORT = 'rechtes Fächerregal bei den Referendar*innen';

	/** Vorschläge für das Feld „Hilfsmittel“. */
	public const HILFSMITTEL_VORSCHLAEGE = [
		'keine',
		'Taschenrechner',
		'Formelsammlung',
		'zweisprachiges Wörterbuch',
		'einsprachiges Wörterbuch',
		'Duden',
		'Gesetzestexte',
	];

	/** Option-Schlüssel (in mh_fw_settings) für das Vorgabe-Kontingent je Art. */
	public static function kontingent_option_key( string $typ ): string {
		return 'ns_kontingent_' . $typ;
	}

	/** @var array<string,array>|null Typen mit den Einstellungen aus dem Backend. */
	private ?array $typen = null;

	/**
	 * @return array<string,array> Alle Arten, Schlüssel = Kürzel (kurz|lang|samstag).
	 */
	public function all(): array {
		if ( null === $this->typen ) {
			// Das Vorgabe-Kontingent ist im Backend einstellbar (Einstellungen →
			// „Vorgabe-Kontingent“). Fehlt der Wert oder ist er ungültig, gilt die Konstante.
			$options     = get_option( 'mh_fw_settings', [] );
			$options     = is_array( $options ) ? $options : [];
			$this->typen = self::TYPEN;
			foreach ( $this->typen as $typ => &$def ) {
				$raw = $options[ self::kontingent_option_key( $typ ) ] ?? '';
				if ( is_numeric( $raw ) && (int) $raw >= 0 && (int) $raw <= 999 ) {
					$def['kontingent'] = (int) $raw;
				}
			}
			unset( $def );
		}
		return $this->typen;
	}

	/**
	 * Werkseinstellung des Kontingents - für die Vorbelegung des Einstellungsfelds.
	 */
	public static function kontingent_werk( string $typ ): int {
		return (int) ( self::TYPEN[ $typ ]['kontingent'] ?? 0 );
	}

	public function exists( string $typ ): bool {
		return isset( self::TYPEN[ $typ ] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( string $typ ): ?array {
		return $this->all()[ $typ ] ?? null;
	}

	/**
	 * @return int[] Vorschläge für die Arbeitszeit.
	 */
	public function durations( string $typ ): array {
		return self::TYPEN[ $typ ]['durations'] ?? [];
	}

	/**
	 * Postfach für einen konkreten Termin - beim kurzen Termin hängt es am Wochentag.
	 */
	public function postfach( string $typ, string $datum_ymd ): string {
		$p = self::TYPEN[ $typ ]['postfach'] ?? '';
		if ( is_array( $p ) ) {
			$wd = (int) date( 'N', (int) strtotime( $datum_ymd ) );
			return (string) ( $p[ $wd ] ?? reset( $p ) );
		}
		return (string) $p;
	}

	/**
	 * Uhrzeit als Anzeigetext: „14:50–16:20 Uhr“, „ab 09:00 Uhr“ oder Hinweis, dass sie
	 * noch festgelegt wird.
	 */
	public static function zeit_label( string $von, string $bis ): string {
		if ( '' !== $von && '' !== $bis ) {
			return $von . '–' . $bis . ' Uhr';
		}
		if ( '' !== $von ) {
			return 'ab ' . $von . ' Uhr';
		}
		return 'Beginn wird je Termin festgelegt';
	}
}
