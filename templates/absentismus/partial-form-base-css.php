<?php
/**
 * Gemeinsames Basis-CSS für alle Absentismus-Formular-Ansichten (Fall eröffnen,
 * Fall-Schritt bearbeiten, Einzelformular ohne Fall). Bewusst selbst-enthalten
 * (kein Verlass auf WordPress' Backend-Klassen .button/.button-primary, die im
 * Frontend oft nicht geladen sind).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
	.mh-form-wrapper { max-width: 900px; margin: 0 auto; box-sizing: border-box; font-family: inherit; }
	.mh-form-wrapper * { box-sizing: border-box !important; }
	.mh-form-section { background: #f9f9f9; border: 1px solid #ccc; padding: 20px; margin-bottom: 25px; border-radius: 4px; }
	.mh-form-section h4 { margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; color: #333; }
	.mh-grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 15px; }
	.mh-grid-2 { grid-template-columns: 1fr 1fr; }
	.mh-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
	.mh-input-group { display: flex; flex-direction: column; }
	.mh-input-group label { font-weight: bold; margin-bottom: 5px; }
	.mh-input-group input, .mh-input-group select, .mh-input-group textarea { padding: 8px 10px; border: 1px solid #aaa; border-radius: 4px; font-size: 15px; }
	.mh-input-group input[readonly] { background: #e9e9e9; color: #555; }
	.mh-input-group textarea { min-height: 90px; }
	.mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
	.radio-group { display: flex; align-items: flex-start; margin-bottom: 8px; gap: 8px; }
	.checkbox-group { display: flex; align-items: flex-start; margin-bottom: 6px; gap: 8px; }
	.req { color: #d63638; font-weight: bold; margin-left: 3px; }
	.mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 15px 20px; margin-bottom: 20px; }
	.mh-error-box ul { margin: 5px 0 0 20px; }
	.btn-group { margin-top: 20px; }
	.mh-weekday-row { display: flex; gap: 15px; flex-wrap: wrap; margin: 8px 0 8px 26px; }
	.mh-step-name-hint { display: block; font-weight: normal; font-size: 0.85em; color: #666; margin-top: 2px; }

	.mh-btn { display: inline-block; text-decoration: none; font-family: inherit; font-size: 0.85em; font-weight: 500; padding: 7px 14px; border-radius: 4px; border: 1px solid #ccc; color: #333; background: #fff; cursor: pointer; line-height: 1.4; text-align: center; }
	.mh-btn:hover { background: #f2f2f2; border-color: #999; color: #333; }
	.mh-btn-primary { background: #0073aa; border-color: #0073aa; color: #fff; }
	.mh-btn-primary:hover { background: #005d8a; border-color: #005d8a; color: #fff; }
	.mh-btn-large { font-size: 0.95em; padding: 10px 20px; }
</style>
