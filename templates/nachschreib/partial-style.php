<?php
/**
 * Gemeinsame Stile der Nachschreib-Ansichten (Anmeldung und Terminverwaltung).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
	.mh-ns { --ns-blue: #004f9f; --ns-blue-soft: #e8f0fa; --ns-red: #c62828; --ns-green: #1e6b30;
		--ns-border: #d5d9de; --ns-muted: #5f6873; --ns-bg: #f6f7f9;
		max-width: 980px; margin: 0 auto; font-family: inherit; color: #1d2327; }
	.mh-ns *, .mh-ns *::before, .mh-ns *::after { box-sizing: border-box; }
	.mh-ns input, .mh-ns select, .mh-ns textarea, .mh-ns button { font-family: inherit; text-transform: none; letter-spacing: normal; }

	.mh-ns-intro { color: var(--ns-muted); margin: 0 0 22px; }

	.mh-ns-step { background: #fff; border: 1px solid var(--ns-border); border-radius: 8px; margin-bottom: 20px; }
	.mh-ns-step > h3 { display: flex; align-items: center; gap: 12px; margin: 0; padding: 14px 20px;
		border-bottom: 1px solid var(--ns-border); font-size: 1.05em; background: var(--ns-bg); border-radius: 8px 8px 0 0; }
	.mh-ns-step-nr { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;
		border-radius: 50%; background: var(--ns-blue); color: #fff; font-size: 0.9em; flex: 0 0 28px; }
	.mh-ns-step-body { padding: 18px 20px; }
	.mh-ns-step.is-locked .mh-ns-step-body { opacity: 0.55; }
	.mh-ns-hint { color: var(--ns-muted); font-size: 0.9em; margin: 0 0 12px; }

	/* Terminart-Karten */
	.mh-ns-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
	@media (max-width: 760px) { .mh-ns-types { grid-template-columns: 1fr; } }
	.mh-ns-type { position: relative; display: block; border: 2px solid var(--ns-border); border-radius: 8px;
		padding: 14px 14px 12px; cursor: pointer; background: #fff; transition: border-color .15s, box-shadow .15s; margin: 0; }
	.mh-ns-type:hover { border-color: #9fb6d3; }
	.mh-ns-type input { position: absolute; opacity: 0; pointer-events: none; }
	.mh-ns-type.is-selected { border-color: var(--ns-blue); box-shadow: 0 0 0 3px var(--ns-blue-soft); }
	.mh-ns-type.is-selected::after { content: "✓"; position: absolute; top: 10px; right: 12px; color: var(--ns-blue); font-weight: 700; }
	.mh-ns-type-title { display: block; font-weight: 700; margin-bottom: 2px; padding-right: 18px; }
	.mh-ns-type-sub { display: block; font-size: 0.88em; color: var(--ns-muted); margin-bottom: 10px; }
	.mh-ns-type-facts { list-style: none; margin: 0; padding: 0; font-size: 0.85em; }
	.mh-ns-type-facts li { margin: 3px 0; padding-left: 0; }
	.mh-ns-type-facts b { display: inline-block; min-width: 64px; color: var(--ns-muted); font-weight: 600; }
	.mh-ns-type:focus-within { outline: 2px solid var(--ns-blue); outline-offset: 2px; }

	/* Terminauswahl */
	.mh-ns-dates { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
	.mh-ns-date { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; text-align: left;
		border: 2px solid var(--ns-border); border-radius: 8px; background: #fff; padding: 9px 12px; cursor: pointer;
		font-size: 1em; color: inherit; line-height: 1.25; margin: 0; }
	.mh-ns-date:hover { border-color: #9fb6d3; background: #fff; color: inherit; }
	.mh-ns-date.is-selected { border-color: var(--ns-blue); background: var(--ns-blue-soft); }
	.mh-ns-date-main { font-weight: 700; font-size: 1.02em; }
	.mh-ns-date-sub { font-size: 0.78em; color: var(--ns-muted); }
	.mh-ns-date-sub.is-soon { color: #9a5b00; font-weight: 600; }
	.mh-ns-date-note { font-size: 0.78em; color: var(--ns-blue); }
	.mh-ns-date { position: relative; overflow: hidden; }
	.mh-ns-seats { display: inline-block; margin-top: 4px; font-size: 0.76em; font-weight: 600; padding: 1px 7px; border-radius: 10px;
		background: #e7f3ea; color: var(--ns-green); }
	.mh-ns-date.is-low { border-color: #e0b100; background: #fff8dc; }
	.mh-ns-date.is-low .mh-ns-seats { background: #f5d547; color: #5c4400; }
	.mh-ns-date.is-low.is-selected { border-color: #b88f00; box-shadow: 0 0 0 3px #fbe9a6; }
	.mh-ns-date.is-full { border-color: #e3a3a3; background: #fdecec; cursor: not-allowed; color: #8a8f96; }
	.mh-ns-date.is-full:hover { border-color: #e3a3a3; background: #fdecec; }
	.mh-ns-date.is-full .mh-ns-seats { background: var(--ns-red); color: #fff; }
	.mh-ns-date.is-full::after { content: "ausgebucht"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-12deg);
		border: 2px solid var(--ns-red); color: var(--ns-red); background: rgba(255,255,255,.85); font-weight: 800; letter-spacing: 1px;
		text-transform: uppercase; font-size: 0.8em; padding: 2px 8px; border-radius: 4px; pointer-events: none; }
	.mh-ns-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 0.8em; color: var(--ns-muted); margin: 10px 0 0; }
	.mh-ns-legend i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; border: 1px solid; }
	.mh-ns-summary.is-warn { border-left-color: #c62828; background: #fdecec; }
	.mh-ns-free { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
	.mh-ns-free input[type="date"] { padding: 9px 10px; border: 1px solid #9aa3ad; border-radius: 6px; font-size: 1em; }
	.mh-ns-more { margin-top: 10px; background: none; border: 0; color: var(--ns-blue); cursor: pointer; padding: 4px 0; font-size: 0.9em; text-decoration: underline; }

	.mh-ns-summary { margin-top: 16px; border-left: 4px solid var(--ns-blue); background: var(--ns-blue-soft);
		padding: 12px 16px; border-radius: 0 6px 6px 0; font-size: 0.95em; }
	.mh-ns-summary strong { display: block; font-size: 1.05em; margin-bottom: 4px; }
	.mh-ns-summary .mh-ns-deadline { margin-top: 6px; font-weight: 600; }

	.mh-ns-types.is-invalid, #mh-ns-date-area.is-invalid { outline: 2px solid var(--ns-red); outline-offset: 6px; border-radius: 6px; }
	.mh-ns-notice { border-left: 4px solid #dba617; background: #fcf7e6; padding: 10px 14px; border-radius: 0 6px 6px 0; font-size: 0.9em; margin: 0 0 12px; }

	/* Schülerzeilen */
	.mh-ns-row { border: 1px solid var(--ns-border); border-radius: 8px; margin-bottom: 12px; background: #fff; }
	.mh-ns-row-head { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #eceef1; background: #fafbfc; border-radius: 8px 8px 0 0; }
	.mh-ns-row-nr { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px;
		border-radius: 12px; background: #e3e7ec; font-weight: 700; font-size: 0.85em; }
	.mh-ns-row-title { font-weight: 600; font-size: 0.9em; flex: 1; }
	.mh-ns-row-remove { border: 0; background: none; font-size: 1.4em; line-height: 1; color: #8a929b; cursor: pointer; padding: 0 4px; }
	.mh-ns-row-remove:hover { color: var(--ns-red); background: none; }
	.mh-ns-rows.is-single .mh-ns-row-remove { visibility: hidden; }
	/* Feste Zeilen-Ausrichtung: Labels einzeilig, damit alle Eingabefelder auf einer Linie
	   beginnen - ein umbrechendes Label hatte das Lehrkraft-Feld nach unten verschoben. */
	.mh-ns-row-grid { display: grid; grid-template-columns: 0.9fr 1.6fr 0.8fr 1.4fr 0.9fr 1.1fr; gap: 12px; padding: 12px; align-items: start; }
	@media (max-width: 960px) { .mh-ns-row-grid { grid-template-columns: 1fr 1fr; } .mh-ns-f-student { grid-column: span 2; } }
	@media (max-width: 520px) { .mh-ns-row-grid { grid-template-columns: 1fr; } .mh-ns-f-student { grid-column: auto; } }

	.mh-ns-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
	.mh-ns-field > label { display: block !important; font-weight: 600; font-size: 0.82em; line-height: 1.3 !important; color: #3c434a;
		margin: 0 !important; padding: 0 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.mh-ns-custom { display: none !important; }
	.mh-ns-field.is-custom .mh-ns-custom { display: block !important; }
	.mh-ns-field input, .mh-ns-field select, .mh-ns-step textarea {
		width: 100%; height: 40px; padding: 0 10px; border: 1px solid #9aa3ad; border-radius: 6px; background: #fff;
		font-size: 15px; margin: 0; color: inherit; }
	.mh-ns-field select:disabled { background: #f0f1f3; color: #8a929b; }
	.mh-ns-field input:focus, .mh-ns-field select:focus, .mh-ns-step textarea:focus { outline: none; border-color: var(--ns-blue); box-shadow: 0 0 0 2px var(--ns-blue-soft); }
	.mh-ns-f-teacher input { text-transform: uppercase; }
	.mh-ns-field select { text-overflow: ellipsis; }
	.mh-ns-name-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
	.mh-ns-manual { display: none; }
	.mh-ns-field.is-manual .mh-ns-manual { display: block; }
	.mh-ns-field.is-manual .mh-ns-name-pair.mh-ns-manual { display: grid; }
	.mh-ns-f-class.is-manual select.js-class:not([type=hidden]) { margin-bottom: 0; }
	.mh-ns-field.is-invalid input:not([type=hidden]), .mh-ns-field.is-invalid select { border-color: var(--ns-red); background: #fff6f6; }
	.mh-ns-field.is-invalid label { color: var(--ns-red); }

	.mh-ns-add { display: inline-flex; align-items: center; gap: 6px; border: 1px dashed #7d8b9a; background: #fff; color: var(--ns-blue);
		border-radius: 6px; padding: 9px 16px; cursor: pointer; font-size: 0.95em; font-weight: 600; }
	.mh-ns-add:hover { background: var(--ns-blue-soft); color: var(--ns-blue); }
	.mh-ns-add-hint { color: var(--ns-muted); font-size: 0.85em; margin-left: 10px; }

	/* Bestätigen */
	.mh-ns-check { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--ns-border); border-radius: 6px; margin: 0 0 10px; cursor: pointer; font-weight: 400; }
	.mh-ns-check input { width: 18px; height: 18px; margin: 2px 0 0; flex: 0 0 18px; }
	.mh-ns-check.is-invalid { border-color: var(--ns-red); background: #fff6f6; }
	.mh-ns-step textarea { height: 70px; padding: 8px 10px; }
	.mh-ns-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 18px; }
	.mh-ns-btn { display: inline-block; border: 1px solid var(--ns-border); background: #fff; color: #1d2327 !important; text-decoration: none !important;
		border-radius: 6px; padding: 9px 16px; font-size: 0.95em; cursor: pointer; line-height: 1.3; }
	.mh-ns-btn:hover { background: #f2f4f6; }
	.mh-ns-btn-primary { background: var(--ns-blue); border-color: var(--ns-blue); color: #fff !important; font-weight: 600; padding: 12px 22px; font-size: 1.02em; }
	.mh-ns-btn-primary:hover { background: #003c7a; }
	.mh-ns-btn-small { padding: 5px 10px; font-size: 0.82em; }
	.mh-ns-btn-danger { color: var(--ns-red) !important; }

	/* Meldungen */
	.mh-ns-errors { border-left: 5px solid var(--ns-red); background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 14px 18px; margin-bottom: 20px; border-radius: 0 6px 6px 0; }
	.mh-ns-errors h3 { margin: 0 0 6px; color: var(--ns-red); font-size: 1.05em; }
	.mh-ns-errors ul { margin: 0 0 0 18px; padding: 0; }
	.mh-ns-success { border: 1px solid #b7dfc0; background: #f0f9f2; border-radius: 8px; padding: 18px 20px; margin-bottom: 24px; }
	.mh-ns-success h3 { margin: 0 0 8px; color: var(--ns-green); }
	.mh-ns-success ol { margin: 10px 0 14px 20px; padding: 0; }
	.mh-ns-success li { margin: 4px 0; }
	.mh-ns-flash { background: #f0f9f2; border-left: 4px solid var(--ns-green); padding: 8px 14px; margin-bottom: 12px; }

	/* Liste */
	.mh-ns-list { width: 100%; border-collapse: collapse; font-size: 0.92em; }
	.mh-ns-list th, .mh-ns-list td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #eceef1; vertical-align: top; }
	.mh-ns-list th { font-size: 0.82em; color: var(--ns-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
	.mh-ns-list tr.is-past td { color: #8a929b; }
	.mh-ns-list td.mh-ns-list-actions { white-space: nowrap; text-align: right; }
	.mh-ns-list td.mh-ns-list-actions form { display: inline; margin: 0; }
	.mh-ns-tag { display: inline-block; font-size: 0.75em; padding: 1px 7px; border-radius: 10px; background: #e3e7ec; margin-left: 4px; }
	@media (max-width: 640px) { .mh-ns-list .mh-ns-col-created { display: none; } }

	/* Reiter (nur für berechtigte Lehrkräfte) */
	.mh-ns-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--ns-border); margin: 0 0 22px; }
	.mh-ns-tabs a { padding: 10px 18px; text-decoration: none !important; color: var(--ns-muted) !important; font-weight: 600;
		border: 2px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; margin-bottom: -2px; }
	.mh-ns-tabs a.is-active { color: var(--ns-blue) !important; background: #fff; border-color: var(--ns-border); border-bottom: 2px solid #fff; }
	.mh-ns-tabs a:hover { color: var(--ns-blue) !important; }
</style>
