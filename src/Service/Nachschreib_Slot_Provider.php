<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

use DateTimeImmutable;
use Mh\FormWorkflows\Repository\Nachschreib_Termin_Repository;
use Mh\FormWorkflows\Repository\Submission_Repository;

/**
 * Class Nachschreib_Slot_Provider
 *
 * Termine = Vorschlagsliste aus dem Katalog + Abweichungen aus der Terminverwaltung.
 * - kurz:    jeder Schul-Mittwoch und -Donnerstag, standardmäßig freigegeben
 * - samstag: jeder Samstag außerhalb der Ferien, standardmäßig NICHT freigegeben
 * - lang:    nur die angelegten Termine
 *
 * Belegt = Zahl der angemeldeten Schüler*innen (nicht der Anmeldungen), gezählt aus den
 * gespeicherten Anmeldungen. Es gibt keine separate Reservierung - eine Anmeldung IST die
 * Platzbuchung.
 */
class Nachschreib_Slot_Provider implements Nachschreib_Slot_Provider_Interface {

	/** Wie weit die Vorschlagsliste für die Anmeldung reicht. */
	private const WEEKS_AHEAD = 10;

	/** Angelegte (lange) Termine werden höchstens so weit im Voraus angeboten. */
	private const CONFIGURED_DAYS_AHEAD = 200;

	private const WOCHENTAGE      = [ 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag' ];
	private const WOCHENTAGE_KURZ = [ 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So' ];

	/** @var array<string,array> Belegung je "typ|exclude_id". */
	private array $belegung_cache = [];

	public function __construct(
		private Nachschreib_Termin_Katalog $katalog,
		private School_Date_Calculator $calendar,
		private Nachschreib_Termin_Repository $termine,
		private Submission_Repository $submissions
	) {}

	public function get_slots( string $typ, DateTimeImmutable $now, int $exclude_id = 0 ): array {
		$def = $this->katalog->get( $typ );
		if ( null === $def ) {
			return [];
		}

		$horizon = 'weekly' === $def['slots'] ? self::WEEKS_AHEAD * 7 : self::CONFIGURED_DAYS_AHEAD;
		$slots   = [];
		foreach ( $this->candidates( $typ, $now, $horizon ) as $ymd ) {
			$slot = $this->build( $typ, $ymd, $exclude_id );
			if ( null !== $slot && $slot['aktiv'] && ! $this->is_past_deadline( $slot, $now ) ) {
				$slots[] = $slot;
			}
		}
		return $slots;
	}

	public function get_verwaltung( string $typ, DateTimeImmutable $now, int $weeks ): array {
		$def = $this->katalog->get( $typ );
		if ( null === $def ) {
			return [];
		}
		$out = [];
		foreach ( $this->candidates( $typ, $now, max( 1, $weeks ) * 7 ) as $ymd ) {
			$slot = $this->build( $typ, $ymd, 0 );
			if ( null === $slot ) {
				continue;
			}
			$slot['frist_abgelaufen'] = $this->is_past_deadline( $slot, $now );
			$slot['abweichung']       = $this->termine->find( $typ, $ymd );
			$slot['vorgabe']          = [
				'aktiv'      => (bool) $def['default_aktiv'],
				'zeit_von'   => (string) $def['zeit_von'],
				'zeit_bis'   => (string) $def['zeit_bis'],
				'raum'       => (string) $def['raum'],
				'kontingent' => (int) $def['kontingent'],
			];
			$out[] = $slot;
		}
		return $out;
	}

	public function allows_free_date( string $typ ): bool {
		$def = $this->katalog->get( $typ );
		if ( null === $def || 'configured' !== $def['slots'] || empty( $def['free_fallback'] ) ) {
			return false;
		}
		$today = current_time( 'Y-m-d' );
		foreach ( $this->termine->get_by_typ( $typ ) as $ymd => $row ) {
			if ( $ymd >= $today && $row['aktiv'] ) {
				return false;
			}
		}
		return true;
	}

	public function find_slot( string $typ, string $datum_ymd, int $exclude_id = 0, bool $auch_inaktiv = false ): ?array {
		$slot = $this->build( $typ, $datum_ymd, $exclude_id );
		return ( null !== $slot && ( $slot['aktiv'] || $auch_inaktiv ) ) ? $slot : null;
	}

	public function is_past_deadline( array $slot, DateTimeImmutable $now ): bool {
		$frist = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', (string) ( $slot['frist'] ?? '' ), $now->getTimezone() );
		return false === $frist || $now > $frist;
	}

	/**
	 * Mögliche Tage einer Art im Zeitraum, aufsteigend.
	 *
	 * @return string[]
	 */
	private function candidates( string $typ, DateTimeImmutable $now, int $days ): array {
		$def   = $this->katalog->get( $typ );
		$today = $now->format( 'Y-m-d' );
		$limit = $now->modify( '+' . $days . ' days' )->format( 'Y-m-d' );

		if ( 'weekly' !== $def['slots'] ) {
			return array_values( array_filter(
				array_keys( $this->termine->get_by_typ( $typ ) ),
				static fn( string $d ): bool => $d >= $today && $d <= $limit
			) );
		}

		$out = [];
		for ( $day = $now->setTime( 0, 0 ); $day->format( 'Y-m-d' ) <= $limit; $day = $day->modify( '+1 day' ) ) {
			$out[] = $day->format( 'Y-m-d' );
		}
		return $out;
	}

	/**
	 * Baut den wirksamen Slot (Vorgabe + Abweichung + Belegung) - null, wenn an dem Tag
	 * grundsätzlich kein Termin dieser Art möglich ist.
	 */
	private function build( string $typ, string $ymd, int $exclude_id ): ?array {
		$def = $this->katalog->get( $typ );
		if ( null === $def || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return null;
		}
		[ $y, $m, $d ] = array_map( 'intval', explode( '-', $ymd ) );
		if ( ! checkdate( $m, $d, $y ) ) {
			return null;
		}

		$weekday = (int) date( 'N', (int) strtotime( $ymd ) );
		if ( ! in_array( $weekday, $def['weekdays'], true ) || ! $this->is_possible_day( $weekday, $ymd ) ) {
			return null;
		}

		$row           = $this->termine->find( $typ, $ymd );
		$frei_gewaehlt = false;
		if ( null === $row && 'configured' === $def['slots'] ) {
			if ( ! $this->allows_free_date( $typ ) ) {
				return null;
			}
			$frei_gewaehlt = true;
		}

		$aktiv      = null !== $row ? $row['aktiv'] : (bool) $def['default_aktiv'];
		$zeit_von   = '' !== ( $row['zeit_von'] ?? '' ) ? $row['zeit_von'] : (string) $def['zeit_von'];
		$zeit_bis   = '' !== ( $row['zeit_bis'] ?? '' ) ? $row['zeit_bis'] : (string) $def['zeit_bis'];
		$raum       = '' !== ( $row['raum'] ?? '' ) ? $row['raum'] : (string) $def['raum'];
		$kontingent = $row['kontingent'] ?? (int) $def['kontingent'];
		$belegt     = $this->belegung( $typ, $exclude_id )[ $ymd ]['schueler'] ?? 0;
		$rest       = max( 0, $kontingent - $belegt );

		$frist = $this->deadline_for( $ymd, (int) $def['frist_tage'] );
		$ts    = (int) strtotime( $ymd );
		$fts   = (int) strtotime( $frist );

		return [
			'typ'           => $typ,
			'datum'         => $ymd,
			'label'         => self::WOCHENTAGE[ $weekday ] . ', ' . date( 'd.m.Y', $ts ),
			'label_kurz'    => self::WOCHENTAGE_KURZ[ $weekday ] . ' ' . date( 'd.m.', $ts ),
			'zeit_von'      => $zeit_von,
			'zeit_bis'      => $zeit_bis,
			'zeit'          => Nachschreib_Termin_Katalog::zeit_label( $zeit_von, $zeit_bis ),
			'raum'          => $raum,
			'hinweis'       => (string) ( $row['hinweis'] ?? '' ),
			'frist'         => $frist,
			'frist_label'   => self::WOCHENTAGE_KURZ[ (int) date( 'N', $fts ) ] . ' ' . date( 'd.m.', $fts ) . ', ' . date( 'H:i', $fts ) . ' Uhr',
			'postfach'      => $this->katalog->postfach( $typ, $ymd ),
			'aktiv'         => $aktiv,
			'kontingent'    => $kontingent,
			'belegt'        => $belegt,
			'rest'          => $rest,
			'ausgebucht'    => $rest <= 0,
			'frei_gewaehlt' => $frei_gewaehlt,
		];
	}

	/**
	 * @return array<string,array{anmeldungen:int,schueler:int}>
	 */
	private function belegung( string $typ, int $exclude_id ): array {
		$key = $typ . '|' . $exclude_id;
		if ( ! isset( $this->belegung_cache[ $key ] ) ) {
			$this->belegung_cache[ $key ] = $this->submissions->get_nachschreib_belegung( $typ, $exclude_id );
		}
		return $this->belegung_cache[ $key ];
	}

	/**
	 * Werktage müssen Schultage sein; ein Samstag darf nur nicht in den Ferien oder auf
	 * einem Feiertag liegen.
	 */
	private function is_possible_day( int $weekday, string $ymd ): bool {
		if ( 6 === $weekday ) {
			return ! $this->calendar->is_ferien_oder_feiertag_ymd( $ymd );
		}
		return $this->calendar->is_school_day_ymd( $ymd );
	}

	/**
	 * Abgabeschluss: N Kalendertage vorher, 11:00 Uhr. Ist das kein Schultag (Wochenende,
	 * Feiertag, Ferien), gilt der Schultag davor - dann ist das Postfach auch erreichbar.
	 */
	private function deadline_for( string $ymd, int $tage ): string {
		$day = ( new DateTimeImmutable( $ymd ) )->modify( '-' . $tage . ' days' );
		for ( $i = 0; $i < 30 && ! $this->calendar->is_school_day_ymd( $day->format( 'Y-m-d' ) ); $i++ ) {
			$day = $day->modify( '-1 day' );
		}
		return $day->format( 'Y-m-d' ) . ' 11:00';
	}
}
