<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

use wpdb;

/**
 * Class Absentismus_Fall_Repository
 *
 * Verwaltet Absentismus-Fälle (Fehlzeiten-Eskalationsverfahren) in der bestehenden
 * Tabelle mh_form_submissions (form_type = 'absentismus_fall_v1'). Ein Fall ist eine
 * Zeile, deren form_data-JSON einen fortlaufenden "steps"-Verlauf trägt.
 */
class Absentismus_Fall_Repository {

	private const FORM_TYPE = 'absentismus_fall_v1';

	/**
	 * Regel-Tabelle pro Schritt-Typ — einzige Quelle der Wahrheit für Verfügbarkeit
	 * UND die zugehörigen Klartext-Beschreibungen ("Vergehen"). Der reale
	 * Absentismus-Prozess ist KEINE lineare Kette, sondern ein Graph mit drei
	 * weitgehend unabhängigen Strängen:
	 *
	 * 1. Eskalationskette (kumulative Std.): gespraech_1 -> gespraech_2 -> mahnung,
	 *    danach VERZWEIGT es in bussgeld UND teilkonferenz (beide unabhängig).
	 * 2. Zusammenhängende Fehltage (parallel, an is_schulpflichtig gekoppelt,
	 *    keine Abhängigkeit zur Eskalationskette): ordnungsamt (NUR schulpflichtig —
	 *    Ordnungsamt-Zuführung ist der Durchsetzungsmechanismus während der
	 *    Schulpflicht), beendigung_47 (NUR NICHT MEHR schulpflichtig — das
	 *    Schulverhältnis kann nur beendet werden, wenn keine Schulpflicht mehr
	 *    besteht; bei bestehender Schulpflicht greift stattdessen Ordnungsamt).
	 * 3. Entschuldigt-Strang (unabhängig von 1+2): attestauflage.
	 *
	 * Ein Schritt-Typ kann mehrere 'variants' haben — verschiedene, jeweils
	 * eigenständig ausreichende Auslöser für DASSELBE Formular (z. B. beendigung_47:
	 * entweder eine eigenständige 15-Tage-Fehlserie ODER weil die komplette
	 * Eskalationskette bereits mit einer Teilkonferenz-Entlassung endete). Jede
	 * Variante wird in der Timeline als eigene Karte angezeigt (verfügbar oder
	 * gesperrt mit Begründung), auch wenn beide letztlich denselben Formular-Typ
	 * anlegen.
	 *
	 * Je Variante:
	 *   requires:    mind. ein FESTGESCHRIEBENER Schritt jedes genannten Typs muss existieren.
	 *   step_match:  optional — zusätzlich muss ein FESTGESCHRIEBENER Schritt vom
	 *                genannten Typ existieren, dessen data[field] === value ist
	 *                (für Bedingungen, die nicht nur "Typ X wurde gemacht", sondern
	 *                ein konkretes Ergebnis dieses Schritts voraussetzen).
	 *   condition:   Klartext-Beschreibung des Vergehens/Anlasses für die Anzeige.
	 *   locked_hint: optionaler, vorformulierter Hinweistext für den gesperrten
	 *                Zustand (überschreibt den generischen "X muss festgeschrieben
	 *                werden"-Text, z. B. wenn step_match gesetzt ist).
	 *
	 * Je Typ:
	 *   repeatable:      darf nach Festschreibung erneut angelegt werden (neue Fehltage-Serie).
	 *   applicable_when: Schlüssel in den Fall-Stammdaten, der truthy sein muss. Ein
	 *                    führendes "!" negiert die Bedingung (z. B. "!is_schulpflichtig").
	 */
	public const STEP_RULES = [
		'gespraech_1'   => [
			'repeatable' => false, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [], 'condition' => 'ca. 10 unentschuldigte Fehlstunden (kumulativ)' ],
			],
		],
		'gespraech_2'   => [
			'repeatable' => false, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [ 'gespraech_1' ], 'condition' => 'weitere ca. 10 unentschuldigte Fehlstunden (kumulativ)' ],
			],
		],
		'mahnung'       => [
			'repeatable' => false, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [ 'gespraech_2' ], 'condition' => 'weiterhin unentschuldigte Fehlstunden nach dem 2. Gespräch' ],
			],
		],
		'bussgeld'      => [
			'repeatable' => false, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [ 'mahnung' ], 'condition' => 'weitere unentschuldigte Fehlstunden (allgemeine Eskalation)' ],
			],
		],
		'teilkonferenz' => [
			'repeatable' => false, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [ 'mahnung' ], 'condition' => '20 unentschuldigte Fehlstunden innerhalb von 30 Tagen' ],
			],
		],
		'ordnungsamt'   => [
			'repeatable' => true, 'applicable_when' => 'is_schulpflichtig',
			'variants' => [
				[ 'requires' => [], 'condition' => '3 Tage in Folge unentschuldigt gefehlt' ],
			],
		],
		'beendigung_47' => [
			'repeatable' => true, 'applicable_when' => '!is_schulpflichtig',
			'variants' => [
				[ 'requires' => [], 'condition' => '15 Tage in Folge unentschuldigt gefehlt' ],
				[
					'requires'    => [],
					'step_match'  => [ 'type' => 'teilkonferenz', 'field' => 'beschluss', 'value' => 'entlassung' ],
					'condition'   => 'Teilkonferenz hat die Entlassung beschlossen',
					'locked_hint' => 'Wird verfügbar, sobald eine Teilkonferenz mit dem Beschluss "Entlassung" festgeschrieben ist.',
				],
			],
		],
		'attestauflage' => [
			'repeatable' => true, 'applicable_when' => null,
			'variants' => [
				[ 'requires' => [], 'condition' => 'begründete Zweifel an einer krankheitsbedingten (entschuldigten) Abwesenheit' ],
			],
		],
	];

	private string $table_name;

	public function __construct( private wpdb $db ) {
		$this->table_name = $this->db->prefix . 'mh_form_submissions';
	}

	/**
	 * Sucht den offenen Fall eines Schülers (es darf pro Schüler immer nur einen geben).
	 */
	public function find_open_case_by_student( int $student_wu_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE form_type = %s AND student_wu_id = %d AND status = 'offen' LIMIT 1",
			self::FORM_TYPE,
			$student_wu_id
		), ARRAY_A );

		return $this->decode_row( $row );
	}

	public function get_by_id( int $case_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d AND form_type = %s",
			$case_id,
			self::FORM_TYPE
		), ARRAY_A );

		return $this->decode_row( $row );
	}

	/**
	 * Eröffnet einen neuen Fall. Der erste Schritt-Typ wird vom Aufrufer übergeben —
	 * ein Fall kann laut Prozess auch direkt über 'ordnungsamt', 'beendigung_47' oder
	 * 'attestauflage' beginnen, nicht zwingend über 'gespraech_1'. Der Aufrufer muss
	 * vorab selbst gegen get_available_step_types() auf einem virtuellen leeren Case
	 * geprüft haben, dass der gewählte Typ als Einstieg zulässig ist.
	 *
	 * @param array $case_meta Stammdaten: student_wu_id, lastname, firstname, class_wu_id,
	 *                          class_name, teacher, is_minor, is_schulpflichtig.
	 * @param string $first_step_type Slug des ersten Schritts (einer von STEP_RULES).
	 * @param array $first_step_data Typ-spezifische Felder des ersten Schritts.
	 */
	public function create_case( array $case_meta, string $first_step_type, array $first_step_data, int $created_by ): int {
		$now = current_time( 'mysql' );

		$form_data = array_merge( $case_meta, [
			'case_status'        => 'offen',
			'case_closed_at'     => null,
			'case_closed_by'     => null,
			'case_closed_reason' => null,
			'steps'              => [ $this->build_step( 1, $first_step_type, $first_step_data, $created_by, $now ) ],
		] );

		$inserted = $this->db->insert( $this->table_name, [
			'form_type'     => self::FORM_TYPE,
			'status'        => 'offen',
			'user_id'       => $created_by,
			'student_wu_id' => (int) ( $case_meta['student_wu_id'] ?? 0 ),
			'form_data'     => wp_json_encode( $form_data ),
			'created_at'    => $now,
			'updated_at'    => $now,
		] );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Ermittelt alle Vergehen-Varianten, die grundsätzlich zu diesem Fall passen
	 * (d. h. 'applicable_when' erfüllt, nicht wiederholbar+schon erledigt
	 * ausgeschlossen, kein offener Entwurf desselben Typs) — inklusive derer, deren
	 * Vorbedingung noch nicht erfüllt ist. EIN Schritt-Typ kann mehrere Einträge
	 * liefern (eine pro Variante, siehe STEP_RULES), z. B. 'beendigung_47' einmal
	 * für die eigenständige 15-Tage-Fehlserie und einmal für die durchlaufene
	 * Eskalationskette — beide führen zum selben Formular, werden aber als
	 * getrennte Karten angezeigt. Grundlage für die Timeline-Anzeige ("was kommt
	 * als Nächstes, ggf. gesperrt mit Begründung") UND für get_available_step_types().
	 *
	 * @return array<int, array{type: string, condition: string, available: bool, missing: string[], locked_hint: ?string, sequential: bool}>
	 */
	public function get_step_type_overview( array $case ): array {
		if ( 'offen' !== ( $case['status'] ?? 'offen' ) ) {
			return [];
		}

		$steps           = $case['form_data']['steps'] ?? [];
		$finalized_steps = array_values( array_filter( $steps, static fn( array $s ): bool => 'final' === $s['status'] ) );
		$finalized_types = array_unique( array_column( $finalized_steps, 'type' ) );
		$draft_types     = array_unique( array_column( array_filter( $steps, static fn( array $s ): bool => 'draft' === $s['status'] ), 'type' ) );

		$overview = [];
		foreach ( self::STEP_RULES as $type => $rule ) {
			if ( in_array( $type, $draft_types, true ) ) {
				continue; // bereits als eigene Karte in der Timeline sichtbar
			}
			if ( ! $rule['repeatable'] && in_array( $type, $finalized_types, true ) ) {
				continue; // nicht wiederholbar, schon erledigt — nie mehr relevant
			}
			if ( null !== $rule['applicable_when'] && ! $this->condition_met( $rule['applicable_when'], $case['form_data'] ?? [] ) ) {
				continue; // gilt für diesen Fall grundsätzlich nicht (z. B. schulpflichtig-Status)
			}

			foreach ( $rule['variants'] as $variant ) {
				$missing = array_values( array_diff( $variant['requires'], $finalized_types ) );

				$step_match_ok = true;
				if ( ! empty( $variant['step_match'] ) ) {
					$step_match_ok = $this->has_finalized_step_matching( $finalized_steps, $variant['step_match'] );
				}

				$overview[] = [
					'type'        => $type,
					'condition'   => $variant['condition'] ?? '',
					'available'   => empty( $missing ) && $step_match_ok,
					'missing'     => $missing,
					'locked_hint' => ( ! $step_match_ok ) ? ( $variant['locked_hint'] ?? null ) : null,
					// 'sequential' = diese Variante hat eine echte Vorbedingung (requires
					// und/oder step_match) und ist damit Teil einer Rangfolge — im
					// Gegensatz zu eigenständig auslösbaren Vergehen (kein requires,
					// kein step_match), die jederzeit unabhängig vom bisherigen Fall-
					// verlauf zutreffen können (z. B. Ordnungsamt, Attestauflage).
					'sequential'  => ! empty( $variant['requires'] ) || ! empty( $variant['step_match'] ),
				];
			}
		}

		return $overview;
	}

	private function has_finalized_step_matching( array $finalized_steps, array $match ): bool {
		foreach ( $finalized_steps as $step ) {
			if ( $step['type'] === $match['type'] && ( $step['data'][ $match['field'] ] ?? null ) === $match['value'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ermittelt alle Schritt-Typen, die aktuell zu diesem Fall hinzugefügt werden
	 * dürfen (0, 1 oder mehrere — die drei Stränge sind weitgehend unabhängig, z. B.
	 * können 'bussgeld' UND 'teilkonferenz' gleichzeitig verfügbar sein). Ein Typ
	 * gilt als verfügbar, sobald MINDESTENS EINE seiner Varianten verfügbar ist.
	 * Funktioniert auch mit einem virtuellen Case-Array (leere 'steps', nur
	 * Stammdaten gesetzt), um beim Fall-Eröffnen die zulässigen Einstiegs-Typen
	 * zu ermitteln.
	 *
	 * @return string[]
	 */
	public function get_available_step_types( array $case ): array {
		$types = array_column(
			array_filter( $this->get_step_type_overview( $case ), static fn( array $o ): bool => $o['available'] ),
			'type'
		);
		return array_values( array_unique( $types ) );
	}

	/**
	 * Klartext-Beschreibung der (ersten) Variante je Schritt-Typ — für Kontexte,
	 * in denen (anders als in der Timeline) nur ein einzelner Text pro Typ nötig
	 * ist, z. B. die Einstiegs-Typ-Auswahl beim Fall-Eröffnen.
	 *
	 * @return array<string, string>
	 */
	public function get_condition_labels(): array {
		$labels = [];
		foreach ( self::STEP_RULES as $type => $rule ) {
			$labels[ $type ] = $rule['variants'][0]['condition'] ?? '';
		}
		return $labels;
	}

	/**
	 * Hängt einen neuen Schritt an einen Fall an. Setzt voraus, dass der Fall offen
	 * ist und der Typ in get_available_step_types() enthalten ist (Schutz gegen
	 * manipulierte Requests/Race Conditions). Anders als in der früheren linearen
	 * Sequenz dürfen jetzt mehrere Entwürfe UNTERSCHIEDLICHEN Typs parallel offen
	 * sein (z. B. 'mahnung'-Entwurf und 'ordnungsamt'-Entwurf gleichzeitig, da
	 * unabhängige Stränge).
	 */
	public function append_step( int $case_id, string $type, array $data, int $created_by ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case || 'offen' !== $case['status'] ) {
			return false;
		}

		if ( ! in_array( $type, $this->get_available_step_types( $case ), true ) ) {
			return false;
		}

		$steps   = $case['form_data']['steps'] ?? [];
		$now     = current_time( 'mysql' );
		$steps[] = $this->build_step( count( $steps ) + 1, $type, $data, $created_by, $now );

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'steps' => $steps ] ) );
	}

	/**
	 * Fügt eine Aktennotiz hinzu. Notizen sind ansonsten unveränderlich (kein
	 * Bearbeiten) — nur gezieltes Löschen ist über delete_note() möglich.
	 */
	public function add_note( int $case_id, string $text, int $created_by ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$notes   = $case['form_data']['notes'] ?? [];
		$notes[] = [
			'text'       => $text,
			'created_by' => $created_by,
			'created_at' => current_time( 'mysql' ),
		];

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'notes' => $notes ] ) );
	}

	/**
	 * Löscht eine einzelne Notiz anhand ihres Index im 'notes'-Array (die
	 * Anzeige-Reihenfolge in der Timeline ist umgekehrt — der Index bezieht sich
	 * auf die ursprüngliche, chronologische Speicherreihenfolge, siehe
	 * array_reverse($notes, true) im Template, das die Original-Keys erhält).
	 */
	public function delete_note( int $case_id, int $note_index ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$notes = $case['form_data']['notes'] ?? [];
		if ( ! array_key_exists( $note_index, $notes ) ) {
			return false;
		}

		unset( $notes[ $note_index ] );
		$notes = array_values( $notes );

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'notes' => $notes ] ) );
	}

	/**
	 * Ersetzt die komplette Kontaktliste (Eltern/Betreuer/Betrieb/Schüler) des
	 * Falls. Anders als bei Notizen ist hier ein Voll-Ersatz gewollt — Kontakt-
	 * daten werden korrigiert/aktualisiert, kein Audit-Trail nötig.
	 *
	 * @param array $contacts Liste von ['role' => .., 'name' => .., 'phone' => .., 'email' => .., 'note' => ..]
	 */
	public function update_contacts( int $case_id, array $contacts ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'contacts' => $contacts ] ) );
	}

	/**
	 * Aktualisiert die Daten eines Schritts, der noch im Entwurf ist. Nur der
	 * Ersteller darf hierüber ändern (Berechtigung prüft der Controller).
	 */
	public function update_step( int $case_id, int $step_no, array $data ): bool {
		return $this->write_step_data( $case_id, $step_no, $data, true );
	}

	/**
	 * Admin-Override: ändert einen Schritt unabhängig von seinem Status
	 * (auch nach Festschreibung). Berechtigung prüft der Controller.
	 */
	public function update_step_admin( int $case_id, int $step_no, array $data ): bool {
		return $this->write_step_data( $case_id, $step_no, $data, false );
	}

	private function write_step_data( int $case_id, int $step_no, array $data, bool $require_draft ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$steps = $case['form_data']['steps'] ?? [];
		$found = false;
		foreach ( $steps as &$step ) {
			if ( (int) $step['step_no'] === $step_no ) {
				if ( $require_draft && 'draft' !== $step['status'] ) {
					return false;
				}
				$step['data']       = $data;
				$step['updated_at'] = current_time( 'mysql' );
				$found               = true;
				break;
			}
		}
		unset( $step );

		if ( ! $found ) {
			return false;
		}

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'steps' => $steps ] ) );
	}

	/**
	 * Schreibt einen Schritt fest. Danach darf ihn nur noch ein Admin ändern (das
	 * erzwingt der Fall_Controller über einen Capability-Check, nicht dieses Repository).
	 */
	public function finalize_step( int $case_id, int $step_no, int $user_id ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$steps = $case['form_data']['steps'] ?? [];
		$found = false;
		$now   = current_time( 'mysql' );
		foreach ( $steps as &$step ) {
			if ( (int) $step['step_no'] === $step_no ) {
				if ( 'draft' !== $step['status'] ) {
					return false;
				}
				$step['status']       = 'final';
				$step['finalized_by'] = $user_id;
				$step['finalized_at'] = $now;
				$step['updated_at']   = $now;
				$found                 = true;
				break;
			}
		}
		unset( $step );

		if ( ! $found ) {
			return false;
		}

		return $this->persist( $case_id, array_merge( $case['form_data'], [ 'steps' => $steps ] ) );
	}

	public function close_case( int $case_id, int $closed_by, string $reason = 'manual' ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$now = current_time( 'mysql' );

		return $this->persist( $case_id, array_merge( $case['form_data'], [
			'case_status'        => 'geschlossen',
			'case_closed_at'     => $now,
			'case_closed_by'     => $closed_by,
			'case_closed_reason' => $reason,
		] ), [ 'status' => 'geschlossen' ] );
	}

	public function reopen_case( int $case_id, int $reopened_by ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		return $this->persist( $case_id, array_merge( $case['form_data'], [
			'case_status'        => 'offen',
			'case_closed_at'     => null,
			'case_closed_by'     => null,
			'case_closed_reason' => null,
		] ), [ 'status' => 'offen' ] );
	}

	/**
	 * Archiviert einen Fall (soft-delete): Der Fall bleibt vollständig erhalten
	 * (Nachweispflicht/Audit-Trail), verschwindet aber aus der Standard-Übersicht.
	 * Bewusst kein echtes DELETE — offen/geschlossen (`status`) bleibt unverändert,
	 * `archived_at` ist eine orthogonale Sichtbarkeits-Markierung.
	 */
	public function archive_case( int $case_id, int $archived_by ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		$now = current_time( 'mysql' );

		return $this->persist( $case_id, array_merge( $case['form_data'], [
			'case_archived_at' => $now,
			'case_archived_by' => $archived_by,
		] ), [ 'archived_at' => $now ] );
	}

	public function unarchive_case( int $case_id ): bool {
		$case = $this->get_by_id( $case_id );
		if ( null === $case ) {
			return false;
		}

		return $this->persist( $case_id, array_merge( $case['form_data'], [
			'case_archived_at' => null,
			'case_archived_by' => null,
		] ), [ 'archived_at' => null ] );
	}

	/**
	 * @param int[] $case_ids
	 * @return int Anzahl der erfolgreich archivierten Fälle.
	 */
	public function archive_multiple( array $case_ids, int $archived_by ): int {
		$count = 0;
		foreach ( $case_ids as $case_id ) {
			if ( $this->archive_case( (int) $case_id, $archived_by ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Admin-Fallback bei Wechsel der Klassenleitung während ein Fall offen ist.
	 */
	public function reassign_owner( int $case_id, int $new_user_id ): bool {
		$updated = $this->db->update(
			$this->table_name,
			[ 'user_id' => $new_user_id, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $case_id, 'form_type' => self::FORM_TYPE ],
			[ '%d', '%s' ],
			[ '%d', '%s' ]
		);
		return false !== $updated;
	}

	/**
	 * Übersicht aller Fälle. `status`/`user_id`/`archived` werden per SQL gefiltert
	 * (eigene Spalten), `class_wu_id` erst nach dem JSON-Decode — analog zum Stil
	 * von Submission_Repository::get_filtered_submissions().
	 *
	 * @param array $filters ['status', 'user_id', 'class_wu_id', 'archived' => 'exclude'|'only'|'all']
	 *                       'archived' fehlt oder 'exclude' -> archivierte Fälle standardmäßig ausgeblendet.
	 */
	public function get_all_cases( array $filters = [] ): array {
		$where  = [ 'form_type = %s' ];
		$params = [ self::FORM_TYPE ];

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}

		$archived_filter = $filters['archived'] ?? 'exclude';
		if ( 'only' === $archived_filter ) {
			$where[] = 'archived_at IS NOT NULL';
		} elseif ( 'all' !== $archived_filter ) {
			$where[] = 'archived_at IS NULL';
		}

		$where_clause = implode( ' AND ', $where );
		$query        = $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY created_at DESC",
			...$params
		);

		$cases = array_map( [ $this, 'decode_row' ], $this->db->get_results( $query, ARRAY_A ) );

		if ( ! empty( $filters['class_wu_id'] ) ) {
			$class_wu_id = (int) $filters['class_wu_id'];
			$cases       = array_values( array_filter( $cases, static function ( array $case ) use ( $class_wu_id ): bool {
				return (int) ( $case['form_data']['class_wu_id'] ?? 0 ) === $class_wu_id;
			} ) );
		}

		return $cases;
	}

	/**
	 * Prüft eine 'applicable_when'-Bedingung gegen die Fall-Stammdaten. Ein
	 * führendes "!" negiert (z. B. "!is_schulpflichtig" = nicht mehr schulpflichtig).
	 */
	private function condition_met( string $condition_key, array $form_data ): bool {
		$negate = str_starts_with( $condition_key, '!' );
		if ( $negate ) {
			$condition_key = substr( $condition_key, 1 );
		}
		$value = ! empty( $form_data[ $condition_key ] ?? null );
		return $negate ? ! $value : $value;
	}

	private function build_step( int $step_no, string $type, array $data, int $created_by, string $now ): array {
		return [
			'step_no'      => $step_no,
			'type'         => $type,
			'status'       => 'draft',
			'data'         => $data,
			'created_by'   => $created_by,
			'created_at'   => $now,
			'updated_at'   => $now,
			'finalized_by' => null,
			'finalized_at' => null,
		];
	}

	/**
	 * Schreibt form_data (und optional weitere Spalten wie `status`) für einen Fall.
	 */
	private function persist( int $case_id, array $form_data, array $extra_columns = [] ): bool {
		$columns = array_merge( $extra_columns, [
			'form_data'  => wp_json_encode( $form_data ),
			'updated_at' => current_time( 'mysql' ),
		] );

		$formats = array_map( static fn( $value ): string => is_int( $value ) ? '%d' : '%s', $columns );

		$updated = $this->db->update(
			$this->table_name,
			$columns,
			[ 'id' => $case_id, 'form_type' => self::FORM_TYPE ],
			array_values( $formats ),
			[ '%d', '%s' ]
		);

		return false !== $updated;
	}

	private function decode_row( ?array $row ): ?array {
		if ( null === $row ) {
			return null;
		}
		if ( isset( $row['form_data'] ) && is_string( $row['form_data'] ) ) {
			$row['form_data'] = json_decode( $row['form_data'], true ) ?: [];
		}
		return $row;
	}
}
