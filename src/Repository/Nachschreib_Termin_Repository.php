<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Nachschreib_Termin_Repository
 *
 * Abweichungen einzelner Nachschreibtermine von den Vorgaben des Katalogs.
 *
 * Eine Zeile pro (Art, Datum). Für die Arten mit Vorschlagsliste (kurz, samstag) heißt
 * „keine Zeile“: es gilt die Vorgabe. Eine Zeile entsteht erst, wenn jemand den Tag
 * deaktiviert, freischaltet oder Uhrzeit, Raum, Hinweis oder Kontingent ändert. Leere
 * Felder bedeuten auch dann „Vorgabe“ - so wirkt eine spätere Änderung im Katalog
 * weiter, wo niemand bewusst abgewichen ist. Lange Termine existieren nur als Zeile.
 *
 * kontingent NULL = Vorgabe aus dem Katalog.
 */
class Nachschreib_Termin_Repository {

	private string $table_name;

	/** @var array<string,array<string,array>>|null Zwischenspeicher je Art, Schlüssel Datum. */
	private ?array $cache = null;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'mh_nachschreib_termine';
	}

	public function table_exists(): bool {
		return $this->db->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
	}

	/**
	 * Alle Zeilen einer Art, Schlüssel = Datum (Y-m-d). Die Tabelle ist klein (ein paar
	 * Dutzend Zeilen pro Schuljahr), deshalb wird einmal je Aufruf komplett geladen.
	 *
	 * @return array<string,array>
	 */
	public function get_by_typ( string $typ ): array {
		if ( null === $this->cache ) {
			$this->cache = [];
			if ( $this->table_exists() ) {
				$rows = $this->db->get_results( "SELECT * FROM {$this->table_name} ORDER BY datum ASC", ARRAY_A ) ?: [];
				foreach ( $rows as $row ) {
					$this->cache[ (string) $row['typ'] ][ (string) $row['datum'] ] = $this->normalize( $row );
				}
			}
		}
		return $this->cache[ $typ ] ?? [];
	}

	public function find( string $typ, string $datum ): ?array {
		return $this->get_by_typ( $typ )[ $datum ] ?? null;
	}

	/**
	 * Legt die Abweichung an oder überschreibt sie.
	 *
	 * @param array{aktiv:bool,zeit_von:string,zeit_bis:string,raum:string,hinweis:string,kontingent:?int} $werte
	 */
	public function upsert( string $typ, string $datum, array $werte, int $user_id ): bool {
		if ( ! $this->table_exists() ) {
			return false;
		}
		$now = current_time( 'mysql' );

		// REPLACE würde die ID neu vergeben und created_at verlieren, deshalb ON DUPLICATE KEY.
		$sql = $this->db->prepare(
			"INSERT INTO {$this->table_name}
				(typ, datum, aktiv, zeit_von, zeit_bis, raum, hinweis, kontingent, updated_by, created_at, updated_at)
			 VALUES (%s, %s, %d, %s, %s, %s, %s, " . ( null === $werte['kontingent'] ? 'NULL' : '%d' ) . ", %d, %s, %s)
			 ON DUPLICATE KEY UPDATE aktiv = VALUES(aktiv), zeit_von = VALUES(zeit_von), zeit_bis = VALUES(zeit_bis),
				raum = VALUES(raum), hinweis = VALUES(hinweis), kontingent = VALUES(kontingent),
				updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
			...array_values( array_filter( [
				$typ,
				$datum,
				$werte['aktiv'] ? 1 : 0,
				$werte['zeit_von'],
				$werte['zeit_bis'],
				$werte['raum'],
				$werte['hinweis'],
				$werte['kontingent'],
				$user_id,
				$now,
				$now,
			], static fn( $v ) => null !== $v ) )
		);

		$this->cache = null;
		return false !== $this->db->query( $sql );
	}

	public function delete( string $typ, string $datum ): bool {
		if ( ! $this->table_exists() ) {
			return false;
		}
		$this->cache = null;
		return false !== $this->db->delete( $this->table_name, [ 'typ' => $typ, 'datum' => $datum ], [ '%s', '%s' ] );
	}

	private function normalize( array $row ): array {
		return [
			'typ'        => (string) $row['typ'],
			'datum'      => (string) $row['datum'],
			'aktiv'      => '1' === (string) $row['aktiv'],
			'zeit_von'   => (string) ( $row['zeit_von'] ?? '' ),
			'zeit_bis'   => (string) ( $row['zeit_bis'] ?? '' ),
			'raum'       => (string) ( $row['raum'] ?? '' ),
			'hinweis'    => (string) ( $row['hinweis'] ?? '' ),
			'kontingent' => null === $row['kontingent'] ? null : (int) $row['kontingent'],
		];
	}
}
