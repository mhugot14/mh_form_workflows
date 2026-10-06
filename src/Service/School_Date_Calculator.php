<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

use DateTime;
use DateTimeZone;

class School_Date_Calculator {

	/**
	 * Prüft, ob ein Datum ein Wochenende, Feiertag oder in den Ferien (NRW) ist.
	 * Wenn ja, geht es Tag für Tag zurück, bis ein Schultag gefunden wird.
	 *
	 * @param string $date_str Format Y-m-d
	 * @return string Das korrigierte Datum (Y-m-d)
	 */
	public function get_previous_school_day( string $date_str ): string {
		if ( empty( $date_str ) ) {
			return '';
		}

		try {
			$date = new DateTime( $date_str, new DateTimeZone('Europe/Berlin') );
		} catch ( \Exception $e ) {
			return $date_str;
		}

		// Schleife: Solange kein Schultag ist, gehe einen Tag zurück
		while ( ! $this->is_school_day( $date ) ) {
			$date->modify( '-1 day' );
		}

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Öffentliche Variante von is_school_day() für ISO-Datumsstrings (Y-m-d).
	 * Ungültige Eingaben gelten als kein Schultag.
	 */
	public function is_school_day_ymd( string $date_str ): bool {
		try {
			return $this->is_school_day( new DateTime( $date_str, new DateTimeZone( 'Europe/Berlin' ) ) );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Liegt das Datum in den Ferien oder auf einem Feiertag? Unabhängig vom Wochentag -
	 * gebraucht für Samstagstermine, die per Definition keine Schultage sind.
	 */
	public function is_ferien_oder_feiertag_ymd( string $date_str ): bool {
		try {
			$date = new DateTime( $date_str, new DateTimeZone( 'Europe/Berlin' ) );
		} catch ( \Exception $e ) {
			return true;
		}
		return $this->is_public_holiday( $date ) || $this->is_school_holiday_nrw( $date );
	}

	/**
	 * Prüft logisch: Ist heute Schule?
	 */
	private function is_school_day( DateTime $date ): bool {
		// 1. Wochenende (6=Samstag, 7=Sonntag)
		if ( (int) $date->format( 'N' ) >= 6 ) {
			return false;
		}

		// 2. Feiertage (NRW) & Bewegliche
		if ( $this->is_public_holiday( $date ) ) {
			return false;
		}

		// 3. Schulferien NRW (Hardcoded Periods)
		if ( $this->is_school_holiday_nrw( $date ) ) {
			return false;
		}

		return true;
	}

	private function is_public_holiday( DateTime $date ): bool {
		$y = (int) $date->format( 'Y' );
		$md = $date->format( 'm-d' );

		// Feste Feiertage NRW
		$fixed = [
			'01-01', // Neujahr
			'05-01', // Tag der Arbeit
			'10-03', // Tag der Einheit
			'11-01', // Allerheiligen
			'12-25', // 1. Weihnachtstag
			'12-26', // 2. Weihnachtstag
		];

		if ( in_array( $md, $fixed, true ) ) {
			return true;
		}

		// Bewegliche (Ostern-basiert)
		// easter_date() liefert Unix Timestamp für Ostersonntag Mitternacht
		$easter_ts = easter_date( $y );
		$easter_day = ( new DateTime() )->setTimestamp( $easter_ts );

		// Relevante Offsets:
		// Karfreitag (-2), Ostermontag (+1), Christi Himmelfahrt (+39), 
		// Pfingstmontag (+50), Fronleichnam (+60)
		$offsets = [ -2, 1, 39, 50, 60 ];

		foreach ( $offsets as $days ) {
			$clone = clone $easter_day;
			$clone->modify( "$days days" );
			if ( $clone->format( 'm-d' ) === $md ) {
				return true;
			}
		}

		return false;
	}
/**
	 * Prüft, ob das Datum in einem der Ferienzeiträume liegt.
	 * Wir nutzen String-Vergleiche (Y-m-d), um Zeitzonen-Probleme zu vermeiden.
	 */
	private function is_school_holiday_nrw( DateTime $date ): bool {
		$current_ymd = $date->format( 'Y-m-d' );

		// Zeiträume NRW (Format: YYYY-MM-DD Start bis Ende inkl.)
		// Quelle: schulministerium.nrw
		$holidays = [
			// Schuljahr 24/25
			['2024-12-23', '2025-01-06'], // Weihnachten
			['2025-04-14', '2025-04-26'], // Ostern
			['2025-06-10', '2025-06-10'], // Pfingsten
			['2025-07-14', '2025-08-26'], // Sommer
			['2025-10-13', '2025-10-25'], // Herbst
			['2025-12-22', '2026-01-06'], // Weihnachten
			
			// Schuljahr 2026
            ['2026-03-30', '2026-04-11'], // Ostern
            ['2026-05-26', '2026-05-26'], // Pfingsten
            ['2026-07-20', '2026-09-01'], // Sommer

			// Schuljahr 26/27
			['2026-10-17', '2026-10-31'], // Herbst
			['2026-12-23', '2027-01-06'], // Weihnachten
			['2027-03-22', '2027-04-03'], // Ostern
			['2027-05-18', '2027-05-18'], // Pfingsten
			['2027-07-19', '2027-08-31'], // Sommer
		];

		foreach ( $holidays as $period ) {
			// Lexikalischer String-Vergleich funktioniert bei ISO-Datum (Y-m-d) korrekt
			// "2025-12-22" >= "2025-12-22" ist true.
			if ( $current_ymd >= $period[0] && $current_ymd <= $period[1] ) {
				return true;
			}
		}

		return false;
	}
}