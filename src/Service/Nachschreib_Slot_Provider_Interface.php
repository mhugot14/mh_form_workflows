<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

use DateTimeImmutable;

/**
 * Liefert die Nachschreibtermine samt Kontingent und Belegung.
 *
 * Formular, Model, Terminverwaltung und PDF kennen nur diese Schnittstelle. Eine spätere
 * echte Platzbuchung (Reservierung pro Schüler*in, Warteliste) ersetzt nur die
 * Implementierung.
 *
 * Aufbau eines Slots:
 *   typ, datum (Y-m-d), label („Mittwoch, 14.10.2026“), label_kurz („Mi 14.10.“)
 *   zeit_von, zeit_bis   wirksame Uhrzeit ('' = noch offen)
 *   zeit                 Anzeigetext der Uhrzeit
 *   raum, hinweis        wirksamer Raum, Zusatzhinweis ('' = keiner)
 *   frist, frist_label   Abgabeschluss „Y-m-d H:i“ bzw. „Mo 12.10., 11:00 Uhr“
 *   postfach
 *   aktiv                bool - für die Anmeldung freigegeben
 *   kontingent           int  - Plätze gesamt
 *   belegt               int  - angemeldete Schüler*innen
 *   rest                 int  - freie Plätze (nie negativ)
 *   ausgebucht           bool
 *   frei_gewaehlt        bool - Datum stammt nicht aus der Terminliste (allows_free_date())
 */
interface Nachschreib_Slot_Provider_Interface {

	/**
	 * Termine für die Anmeldung: freigegeben und Abgabefrist offen, aufsteigend.
	 * Ausgebuchte Termine sind enthalten (ausgebucht = true), damit das Formular sie
	 * zeigen kann, statt sie kommentarlos wegzulassen.
	 *
	 * @param int $exclude_id Anmeldung, die bei der Belegung nicht zählt (Bearbeiten).
	 * @return array<int,array<string,mixed>>
	 */
	public function get_slots( string $typ, DateTimeImmutable $now, int $exclude_id = 0 ): array;

	/**
	 * Alle Termine ab heute für die Terminverwaltung - auch deaktivierte und solche mit
	 * abgelaufener Frist. Jeder Eintrag hat zusätzlich 'vorgabe' (Katalogwerte) und
	 * 'abweichung' (gespeicherte Zeile oder null).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_verwaltung( string $typ, DateTimeImmutable $now, int $weeks ): array;

	/**
	 * Darf für diese Art ein Datum frei gewählt werden (weil keine Termine angelegt sind)?
	 */
	public function allows_free_date( string $typ ): bool;

	/**
	 * Slot zu einem Datum, unabhängig von der Frist - null, wenn an diesem Tag kein
	 * freigegebener Termin dieser Art stattfindet.
	 *
	 * @param bool $auch_inaktiv Auch deaktivierte Termine liefern (Buchungsübersicht: dort
	 *                           können noch Anmeldungen von vor der Deaktivierung liegen).
	 * @return array<string,mixed>|null
	 */
	public function find_slot( string $typ, string $datum_ymd, int $exclude_id = 0, bool $auch_inaktiv = false ): ?array;

	/**
	 * Ist die Abgabefrist eines Slots verstrichen?
	 */
	public function is_past_deadline( array $slot, DateTimeImmutable $now ): bool;
}
