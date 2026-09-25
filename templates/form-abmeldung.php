<?php
/**
 * View: Schüler-Abmeldung (Intelligente Version mit WebUntis-Anbindung)
 */

// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';

// Lehrer-Name für Autofüllung
$current_user = wp_get_current_user();
$teacher_default = trim($current_user->first_name . ' ' . $current_user->last_name) ?: $current_user->display_name;

// Überblendung Notensammlung: nur ausblenden, wenn schon mind. eine Note vorliegt
$has_existing_grades = false;
if ( ! empty( $form_data['subjects'] ) ) {
    foreach ( $form_data['subjects'] as $s ) {
        if ( ! empty( $s['grade'] ) ) { $has_existing_grades = true; break; }
    }
}

// Fächer-Vorbelegung aus Schild (Stundentafel + Kurse): darf niemals über bereits
// erfasste Zeilen laufen — weder im Bearbeiten-Modus noch nach einem Validierungs-Reload.
$has_existing_subjects = ! empty( $form_data['subjects'] );

// Grundgerüst bleibt bei 12 Zeilen; gespeicherte Formulare können mehr enthalten,
// weitere Zeilen hängt die Vorbelegung bei Bedarf per JS an.
$subject_row_count = max( 12, count( $form_data['subjects'] ?? [] ) );

// Marker im Noten-Dropdown fuer "Note per Mail anfragen".
$collect_marker = \Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form::GRADE_COLLECT_MARKER;

// Protokoll-Modus. Rueckwaertskompatibel: aeltere Datensaetze kennen nur das
// Haekchen protocol_attached. Ein Altdatensatz OHNE Protokoll bekommt bewusst
// keine Vorauswahl - die Entscheidung soll bewusst getroffen werden, statt ihm
// stillschweigend eine Erklaerung unterzuschieben, die nie abgegeben wurde.
$protocol_mode = $form_data['protocol_mode'] ?? '';
if ( '' === $protocol_mode ) {
    if ( empty( $form_data ) ) {
        $protocol_mode = 'create'; // Neues Formular: bisheriger Normalfall
    } elseif ( isset( $form_data['protocol_attached'] ) && '1' === $form_data['protocol_attached'] ) {
        $protocol_mode = 'create';
    }
}

// Warnung extrahieren
$warning_msg = '';
if ( isset( $form_errors['date_autocorrect'] ) ) {
    $warning_msg = $form_errors['date_autocorrect'];
    unset( $form_errors['date_autocorrect'] );
}
?>

<style>
    /* CSS RESET & LAYOUT (Erhalten & Erweitert) */
    .mh-form-wrapper { max-width: 900px; margin: 0 auto; box-sizing: border-box; font-family: inherit; }
    .mh-form-wrapper * { box-sizing: border-box !important; float: none !important; position: static !important; }
    .mh-form-wrapper .mh-info-icon { position: relative !important; }
    .mh-form-wrapper .mh-info-icon:hover::after { position: absolute !important; }
    
    .mh-form-section { background: #f9f9f9; border: 1px solid #ccc; padding: 20px; margin-bottom: 25px; border-radius: 4px; width: 100% !important; display: block !important; }
    .mh-form-section h4 { margin-top: 0 !important; margin-bottom: 20px !important; border-bottom: 1px solid #ddd !important; padding-bottom: 10px; color: #333; }

    .mh-grid-row { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important; gap: 20px !important; margin-bottom: 15px !important; width: 100% !important; }
    .mh-grid-2 { grid-template-columns: 1fr 1fr !important; }
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr !important; }
    @media (max-width: 768px) { .mh-grid-2, .mh-grid-3 { grid-template-columns: 1fr !important; } }

    .mh-input-group { display: flex !important; flex-direction: column !important; width: 100% !important; margin: 0 !important; border: none !important; padding: 0 !important; }
    .mh-input-group label { display: block !important; width: 100% !important; margin: 0 0 5px 0 !important; font-weight: bold; line-height: 1.4 !important; height: auto !important; }
    
    .mh-input-group input, .mh-input-group select, .mh-input-group textarea {
        display: block !important; width: 100% !important; height: 40px !important; padding: 6px 12px !important; margin: 0 !important; border: 1px solid #aaa !important; background-color: #fff !important; border-radius: 4px !important; font-size: 15px !important;
    }
    .mh-input-group textarea { height: auto !important; }
    .mh-input-group input[readonly] { background-color: #e9e9e9 !important; color: #555 !important; cursor: not-allowed; }
    .mh-fake-input { display: flex !important; align-items: center; height: 40px; width: 100%; background: #e9e9e9; border: 1px solid #aaa; border-radius: 4px; padding: 0 10px; color: #555; }

    .radio-group { display: flex !important; flex-direction: row !important; align-items: flex-start !important; margin-bottom: 8px !important; gap: 10px !important; }
    .radio-group input { width: 18px !important; height: 18px !important; margin-top: 4px !important; flex-shrink: 0; }
    .radio-group label { font-weight: normal !important; margin: 0 !important; display: inline-block !important; }

    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; }
    .mh-warning-box { background: #fff8e5; border-left: 5px solid #e5a912; padding: 20px; margin-bottom: 30px; }
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
    
    .mh-sub-group { margin-left: 28px; padding: 15px; border-left: 3px solid #ddd; background: #fff; margin-bottom: 15px; margin-top: 5px; }
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    .mh-hidden { display: none !important; }
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 12px 24px !important; cursor: pointer; }
    
    .mh-info-icon { display: inline-block; width: 18px; height: 18px; background: #0073aa; color: #fff; border-radius: 50%; text-align: center; line-height: 18px; font-size: 12px; font-weight: bold; cursor: help; margin-left: 5px; }
    .mh-info-icon:hover::after { content: attr(data-tooltip); position: absolute; bottom: 25px; left: -100px; width: 250px; padding: 10px; background: #333; color: #fff; font-size: 12px; font-weight: normal; line-height: 1.4; border-radius: 4px; z-index: 9999; }
    
    .mh-form-wrapper input, .mh-form-wrapper select, .mh-form-wrapper label, .mh-form-wrapper span, .mh-form-wrapper div { text-transform: none !important; font-variant: normal !important; }
	
	/* Kompakte Notentabelle */
    .mh-subject-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px; /* Etwas kleinere Schrift für die Tabelle */
    }
    .mh-subject-table th {
        background: #eee;
        padding: 8px 5px;
        text-align: left;
        border: 1px solid #ccc;
    }
    .mh-subject-table td {
        padding: 4px;
        border: 1px solid #ccc;
        vertical-align: middle;
    }
    /* Zwinge die Inputs in der Tabelle klein zu sein */
    .mh-subject-table input[type="text"],
    .mh-subject-table input[type="number"],
    .mh-subject-table select {
        height: 32px !important;
        font-size: 13px !important;
        padding: 2px 8px !important;
        margin: 0 !important;
    }
    .mh-subject-table input[type="checkbox"] {
        width: 18px !important;
        height: 18px !important;
        margin: 0 auto !important;
        display: block;
    }
	
	/* Notenfeld schmaler machen */
    .mh-subject-table input[name="subj_grade[]"] {
        width: 50px !important; /* Feste schmale Breite */
        text-align: center;
        margin: 0 auto !important;
        display: block;
	}

	/* Überblendung für Notensammlung (Umlaufverfahren) */
	.mh-grades-overlay-wrap { position: relative !important; }
	.mh-grades-overlay {
		position: absolute !important;
		top: 0; left: 0; right: 0; bottom: 0;
		width: 100%; height: 100%;
		background: rgba(255, 255, 255, 0.85);
		z-index: 10;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		text-align: center;
		padding: 20px;
		border-radius: 4px;
	}
	.mh-grades-overlay-box { max-width: 520px; }
	.mh-grades-overlay-box p { font-size: 17px; line-height: 1.6; margin: 0 0 8px 0 !important; color: #222; font-weight: 500; }
	.mh-grades-overlay-box p strong { color: #0073aa; }
	.mh-grades-overlay-box button {
		margin-top: 12px !important;
		background: #0073aa !important;
		color: #fff !important;
		border: none !important;
		padding: 12px 26px !important;
		height: auto !important;
		border-radius: 4px !important;
		font-size: 15px !important;
		font-weight: bold !important;
		cursor: pointer !important;
	}
	.mh-grades-overlay-box button:hover { background: #005a87 !important; }
	/* Höhere Spezifität als .mh-grades-overlay nötig: beide Regeln nutzen !important,
	   und .mh-grades-overlay steht im Stylesheet später, würde .mh-hidden sonst immer schlagen. */
	.mh-grades-overlay.mh-hidden { display: none !important; }
   /* Gehärtetes CSS für die Hilfe-Box */
details.mh-help-notice-box {
    background-color: #f0f6fb !important;
    border: 1px solid #003E7E !important;
    border-left: 5px solid #003E7E !important;
    border-radius: 4px !important;
    margin: 20px 0 !important;
    display: block !important;
    width: 100% !important;
    padding: 0 !important;
}

details.mh-help-notice-box summary {
    padding: 15px !important;
    color: #003E7E !important;
    font-weight: bold !important;
    cursor: pointer !important;
    list-style: none !important;
    display: flex !important;
    align-items: center !important;
    outline: none !important;
    background: none !important;
    border: none !important;
}

/* Standard-Pfeil von Browsern verstecken */
details.mh-help-notice-box summary::-webkit-details-marker {
    display: none !important;
}

/* Eigenes Icon vor den Text setzen */
details.mh-help-notice-box summary::before {
    content: "\f140" !important; /* Dashicon Pfeil */
    font-family: dashicons !important;
    font-size: 20px !important;
    margin-right: 10px !important;
    transition: transform 0.2s ease !important;
}

details.mh-help-notice-box[open] summary::before {
    transform: rotate(180deg) !important;
}

/* Der weiße Inhaltsbereich */
.mh-help-content-inner {
    padding: 0 20px 20px 20px !important;
    background-color: #f0f6fb !important; /* Gleicher Hintergrund wie Box */
    color: #333 !important;
}

.mh-help-content-inner h5 {
    margin: 15px 0 10px 0 !important;
    color: #003E7E !important;
    font-weight: bold !important;
    border-bottom: 1px solid #d1e3ef !important;
    padding-bottom: 5px !important;
}

.mh-help-content-inner ul {
    margin: 0 0 15px 20px !important;
    padding: 0 !important;
    list-style-type: disc !important;
}

.mh-help-content-inner li {
    margin-bottom: 8px !important;
    float: none !important; /* Wichtig gegen Theme-Floats */
}
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box"><h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3></div>
    <?php endif; ?>

    <?php if ( ! empty( $warning_msg ) ): ?>
        <div class="mh-warning-box">
             <h3 style="margin-top:0; color:#b7791f;">⚠️ Hinweis zur Datumsänderung:</h3>
             <div style="color: #8a6d3b; line-height: 1.4;"><?= $warning_msg ?></div>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">❌ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;"><?php foreach($form_errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <input type="hidden" name="form_type" value="abmeldung_student_v1">
        <input type="hidden" name="submission_id" value="<?= $val('id') ?>">
        
        <!-- NEU: Flag für Vollzeit-Logik -->
        <input type="hidden" name="is_fulltime_class" id="is_fulltime_class" value="<?= $val('is_fulltime_class') ?>">

        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- NEU: SEKTION 0: Auswahl aus Stammdaten -->
        <div class="mh-form-section">
            <h4>Klassen- & Schülerwahl</h4>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Klasse <span class="req">*</span></label>
                    <select name="class_wu_id" id="mh_class_select" required>
                        <option value="">-- Bitte wählen --</option>
                        <?php if(!empty($classes_list)): foreach($classes_list as $c): ?>
                            <option value="<?= $c['wu_id'] ?>" 
                                    data-fulltime="<?= $c['is_fulltime'] ?>" 
                                    data-name="<?= esc_attr($c['name']) ?>"
                                    data-track="<?= esc_attr($c['track_key'] ?? '') ?>"
                                    <?= selected($val('class_wu_id'), $c['wu_id']) ?>>
                                <?= esc_html($c['name']) ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <input type="hidden" name="class_name" id="class_name_hidden" value="<?= $val('class_name') ?>">
                </div>

                <div class="mh-input-group">
                    <label>Schüler*in <span class="req">*</span></label>
                    <select name="student_wu_id" id="mh_student_select" required <?= empty($val('class_wu_id')) ? 'disabled' : '' ?>>
						<option value="">-- Erst Klasse wählen --</option>
						<!-- Diese Option erlaubt den manuellen Override -->
						<option value="manual">-- Manueller Eintrag (Schüler*in nicht in der Liste) --</option>
						<?php if(!empty($val('student_wu_id'))): ?>
							<!-- Wir setzen den gespeicherten Schüler als erste Option ein -->
							<option value="<?= $val('student_wu_id') ?>" selected>
								<?= $val('lastname') ?>, <?= $val('firstname') ?>
							</option>
						<?php endif; ?>
					</select>
                    <input type="hidden" name="lastname" id="student_lastname" value="<?= $val('lastname') ?>">
                    <input type="hidden" name="firstname" id="student_firstname" value="<?= $val('firstname') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 1: Stammdaten (Erhalten & Lehrer Autofill) -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            <div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Nachname <span class="req">*</span></label><input type="text" name="lastname_manual" id="display_lastname" readonly class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>"></div>
                <div class="mh-input-group"><label>Vorname <span class="req">*</span></label><input type="text" name="firstname_manual" id="display_firstname" readonly class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>"></div>
                <div class="mh-input-group">
                    <label>Geburtsdatum <span class="req">*</span></label>
                    <input type="date" name="dob" id="field_dob" required readonly class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Klasse (Anzeige)</label><input type="text" id="display_classname" readonly value="<?= $val('class_name') ?>"></div>
                <div class="mh-input-group">
                    <label>Klassenlehrer*in (angemeldet) <span class="req">*</span></label>
                    <input type="text" name="teacher" required readonly value="<?= $val('teacher') ?: $teacher_default ?>">
                </div>  
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Status <span class="mh-info-icon" data-tooltip="Ermittelt Volljährigkeit zum Stichtag 01.08.">?</span></label><div class="mh-fake-input"><span id="status_display">...</span><input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>"></div></div>
                <div class="mh-input-group"><label>Datum der Abmeldung / Kündigung <span class="req">*</span><span class="mh-info-icon" data-tooltip="Datum des Endes des Schulverhältnisses. Das Formular rechnet basierend darauf den letzten Schultag (Konferenz- und Zeugnisdatum) aus.">?</span> </label><input type="date" name="date_off" id="field_date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>"></div>
            </div>
        </div>

        <!-- SEKTION 2: Grund (Erhalten) -->
        <div class="mh-form-section">
            <h4>Grund der Abmeldung <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="reason" value="schulwechsel" id="r_wechsel" class="toggle-trigger" data-target="new_school_wrap" required <?= $chk('reason', 'schulwechsel') ?>> <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule)</label></div>
            <div id="new_school_wrap" class="mh-sub-group toggle-target"><div class="mh-input-group"><label>Name der Schule <span class="req">*</span></label><input type="text" name="new_school" placeholder="Schulname" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>"></div></div>
            <div class="radio-group"><input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> <label for="r_beschl">Ausschulung Beschluss Teillehrerkonferenz</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> <label for="r_abm">Abmeldung</label></div>
        </div>

        <!-- SEKTION 3: Schulpflicht (Erhalten) -->
	    <div class="mh-form-section">
            <h4>Schulpflicht <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="compulsory" value="fulfilled" id="c_full" class="toggle-trigger" required <?= $chk('compulsory', 'fulfilled') ?>> <label for="c_full">Die Schulpflicht ist erfüllt.</label></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="not_fulfilled" id="c_not" class="toggle-trigger" <?= $chk('compulsory', 'not_fulfilled') ?>> <label for="c_not">Die Schulpflicht ist NICHT erfüllt (Schulpflichtverfolgung...).</label></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="av_klasse" id="c_av" class="toggle-trigger" data-target="av_details" <?= $chk('compulsory', 'av_klasse') ?>> <label for="c_av">Wechsel in AV-Klasse</label></div>
            <div id="av_details" class="mh-sub-group toggle-target"><div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Zum Datum <span class="req">*</span></label><input type="date" name="av_date_start" value="<?= $val('av_date_start') ?>"></div>
                <div class="mh-input-group"><label>Gespräch mit <span class="req">*</span></label><input type="text" name="av_talk_with" value="<?= $val('av_talk_with') ?>"></div>
                <div class="mh-input-group"><label>am <span class="req">*</span></label><input type="date" name="av_talk_date" value="<?= $val('av_talk_date') ?>"></div>
            </div></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="bildungsgang" id="c_bg" class="toggle-trigger" data-target="bg_details" <?= $chk('compulsory', 'bildungsgang') ?>> <label for="c_bg">Wechsel in den Bildungsgang...</label></div>
            <div id="bg_details" class="mh-sub-group toggle-target"><div class="mh-input-group"><label>Name des Bildungsgangs <span class="req">*</span></label><input type="text" name="new_education_track" value="<?= $val('new_education_track') ?>"></div></div>
	
		<div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px;">
			<details class="mh-help-notice-box">
				<summary>
					Wie prüfe ich die Schulpflicht? Hinweise & Tipps
				</summary>
				<div class="mh-help-content-inner">

					<h5>Allgemeine Regeln (§ 34-38 SchulG)</h5>
					<ul>
						<li><strong>Volljährig:</strong> Schulpflicht endet mit 18 Jahren (außer bei bestehendem Ausbildungsverhältnis).</li>
						<li><strong>Minderjährig:</strong> Berufsschulpflicht bis zum Ende des Schuljahres, in dem das 18. Lebensjahr vollendet wird.</li>
					</ul>

					<h5>Besonderheit Berufsfachschule (Anlage B)</h5>
					<ul>
						<li><strong>BF I (Erster Abschluss / HS9):</strong> Erfüllt ein Jahr der Berufsschulpflicht. Wer ohne Abschluss abgeht, bleibt schulpflichtig.</li>
						<li>
							<strong>BF II (Erw. Erster Abschluss / HS10):</strong> Mit erfolgreichem Abschluss ist die Schulpflicht in der Regel <strong>erfüllt</strong> (§ 38 Abs. 3). 
							<br><strong style="color: #d63638;">Wichtig: Dies gilt auch, wenn der/die SchülerIn noch unter 18 Jahren alt ist</strong>, sofern kein Ausbildungsverhältnis beginnt.
						</li>
						<li><strong>Abbruch:</strong> Bei Abbruch vor Schuljahresende lebt die Schulpflicht sofort wieder auf!</li>
					</ul>

					<div style="background: #fff8e5; padding: 12px; border-radius: 4px; border: 1px solid #f5e7c1; font-size: 0.95em; display: block !important;">
						<span class="dashicons dashicons-warning" style="color: #d6a100; vertical-align: text-bottom;"></span> 
						<strong>Nachweispflicht:</strong> Bei Schulpflichtigen muss die Aufnahmebestätigung der Folgeschule oder der Ausbildungsvertrag zwingend vorliegen.
					</div>
				</div>
			</details>
		</div>
		
		</div>
		
        <!-- SEKTION: ANSCHLUSSPERSPEKTIVE (Bedingt Pflicht) -->
        <div id="section_perspective" class="mh-form-section">
            <h4 style="margin-bottom:5px;">Anschlussperspektive <span class="req" id="perspective_req">*</span></h4>
            <p style="font-size:0.85em; color:#666; margin-bottom:15px;">Auszufüllen für Vollzeit-Bildungsgänge. Im Speziellen AV, BFI, BFII, HH, KA, WG. </p>
            
            <div class="mh-input-group" style="margin-bottom: 5px !important;">
                <div class="radio-group">
                    <input type="radio" name="perspective" value="exists" id="p_exists" class="toggle-trigger" data-target="perspective_details_wrap" <?= $chk('perspective', 'exists') ?>> 
                    <label for="p_exists"><b>Es liegt eine konkrete Anschlussperspektive vor.</b></label>
                </div>
            </div>

            <div id="perspective_details_wrap" class="mh-sub-group toggle-target" style="background:#f0f0f0;">
                <div class="radio-group"><input type="radio" name="perspective_detail" value="ausbildung" <?= $chk('perspective_detail', 'ausbildung') ?>> <label>unterschriebener Ausbildungsvertrag</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="schule" <?= $chk('perspective_detail', 'schule') ?>> <label>Aufnahmebestätigung einer anderen Schule</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="studium" <?= $chk('perspective_detail', 'studium') ?>> <label>schriftliche Zusage eines Studienplatzes</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="fsj" <?= $chk('perspective_detail', 'fsj') ?>> <label>schriftliche Zusage eines FSJ, FÖJ oder BFD</label></div>
                <div class="radio-group" style="align-items: center;"><input type="radio" name="perspective_detail" value="sonstiges" class="toggle-trigger" data-target="p_other_wrap" <?= $chk('perspective_detail', 'sonstiges') ?>> <label>sonstiges:</label></div>
                <div id="p_other_wrap" class="toggle-target" style="margin-left: 25px; margin-top:5px;"><input type="text" name="perspective_other" placeholder="Bitte angeben..." style="width:100%;" value="<?= $val('perspective_other') ?>"></div>
            </div>

            <div class="mh-input-group" style="margin-top: 15px;">
                <div class="radio-group"><input type="radio" name="perspective" value="none" id="p_none" class="toggle-trigger" <?= $chk('perspective', 'none') ?>> <label for="p_none"><b>Es liegt KEINE konkrete Anschlussperspektive vor.</b></label></div>
                <div style="margin-left: 28px; font-size: 0.85em; color: #6f6f6f;">(Name wird zur Nachverfolgung an die Agentur für Arbeit weitergegeben)</div>
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis (Ohne Fehlstunden) -->
        <div class="mh-form-section" style="<?= isset($form_errors['certificate']) ? 'border:2px solid #d63638;' : '' ?>">
            <h4>3. Zeugnis <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="certificate" value="abgang" id="z_ab" required <?= $chk('certificate', 'abgang') ?>> <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <small>(Ohne Abschluss)</small></label></div>
            <div class="radio-group"><input type="radio" name="certificate" value="ueberweisung" id="z_ue" required <?= $chk('certificate', 'ueberweisung') ?>> <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small>(Wechsel innerhalb der Schulstufe)</small></label></div>

            <div style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px; <?= isset($form_errors['protocol_mode']) ? 'border:2px solid #d63638; padding:10px;' : '' ?>">
                <div style="font-weight:bold; margin-bottom:10px;">Zeugniskonferenzprotokoll <span class="req">*</span></div>

                <div class="radio-group">
                    <input type="radio" name="protocol_mode" value="create" id="prot_mode_create" class="toggle-trigger" data-target="protocol_wrapper" <?= 'create' === $protocol_mode ? 'checked' : '' ?>>
                    <label for="prot_mode_create" style="font-weight:bold;">Zeugniskonferenzprotokoll jetzt erstellen</label>
                </div>

                <div class="radio-group" style="align-items:flex-start; margin-top:12px;">
                    <input type="radio" name="protocol_mode" value="existing" id="prot_mode_existing" class="toggle-trigger" style="margin-top:3px;" <?= 'existing' === $protocol_mode ? 'checked' : '' ?>>
                    <label for="prot_mode_existing" style="line-height:1.45;">Ich lege ein bestehendes Zeugniskonferenzprotokoll bei. Konferenzdatum und Zeugnisdatum werden daraus ersichtlich. Änderungen sind mit der Abteilungsleitung abgesprochen und von ihr abgezeichnet.</label>
                </div>

                <div id="prot_existing_hint" style="margin-top:10px; margin-left:28px; font-size:0.85em; color:#6f6f6f; display:none;">
                    Abschnitt 4 entfällt, dem PDF wird kein Protokoll angehängt. Die digitale Noteneinsammlung
                    ist in diesem Fall nicht möglich, weil die Noten aus dem beigefügten Protokoll stammen.
                </div>
            </div>
        </div>

        <!-- SEKTION 5: Protokoll (Inkl. Fehlstunden) -->
        <div id="protocol_wrapper" class="mh-form-section toggle-target mh-collapsible-section" style="border-left: 5px solid #0073aa;">
            <h4>4. Angaben zum Konferenzprotokoll</h4>
           <!-- Ersetzt die Radio-Buttons für Teilzeit/Vollzeit -->
<input type="hidden" name="prot_type" id="input_prot_type" value="<?= $val('prot_type') ?>">
            <div class="mh-grid-row mh-grid-3" style="margin-top:20px;">
                <div class="mh-input-group">
                    <label>Konferenzdatum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Das Konferenzdatum wird der Einfachheit halber auf das Zeugnisdatum gesetzt. Es sollte ein Schultag sein.">?</span></label>
                    <input type="date" name="prot_date" id="field_prot_date" readonly value="<?= $val('prot_date') ?>" style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>">
                    <?php if ( ! empty( $form_data['prot_was_corrected'] ) ): ?><div style="font-size:0.8em; color:#b7791f; margin-top:3px; font-weight:bold;">ℹ️ Korrigiert auf Schultag.</div><?php endif; ?>
                </div>
                <div class="mh-input-group"><label>Ausgabedatum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Dieses wird automatisch berechnet. Es ist der letzte Schultag, ausgehend vom Abmeldedatum.">?</span></label><input type="date" name="prot_issue_date" id="field_prot_issue_date" readonly value="<?= $val('prot_issue_date') ?>" style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>"></div>
                <div class="mh-input-group"><label>Vorsitzende/r <span class="req">*</span><span class="mh-info-icon" data-tooltip="Der/die Vorsitzende ist in der Regel die Abteilungsleitung des Bildungsgangs.">?</span></label><input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>"></div>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                 <div class="mh-input-group"><label>Raum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Gib hier eine Raumnummer oder das LZ für Lehrerzimmer an.">?</span></label><input type="text" name="prot_room" value="<?= $val('prot_room') ?>"></div>
                 <div class="mh-grid-row mh-grid-2" style="margin-bottom:0 !important; gap: 10px !important;">
                    <div class="mh-input-group"><label>Fehlstunden <span class="req">*</span></label><input type="number" name="missed_hours" value="<?= $val('missed_hours') ?>"></div>
                    <div class="mh-input-group"><label>Unentschuldigt <span class="req">*</span></label><input type="number" name="missed_ue" value="<?= $val('missed_ue') ?>"></div>
                 </div>
            </div>
<!-- SEKTION: FÄCHER & NOTEN -->
        <div style="margin-top: 25px; margin-bottom: 20px;">
            <h5 style="margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Fächer &amp; Noten (Vorausfüllung für Protokoll)</h5>

            <div style="background:#f6f7f7; border:1px solid #dcdcde; border-left:4px solid #0073aa; padding:14px 16px; margin-bottom:15px; font-size:0.9em; line-height:1.5;">
                <p style="margin:0 0 12px;"><strong>Du hast drei Möglichkeiten, an die Noten zu kommen – und kannst pro Fach unterschiedlich entscheiden:</strong></p>

                <div style="margin-bottom:11px;">
                    <strong style="color:#1d2327;">1. Noten im digitalen Formular eintragen</strong><br>
                    Die Note liegt dir schon vor – aus deinem eigenen Unterricht oder weil die Kolleg*in sie dir
                    genannt hat. Wähle sie in der Spalte <em>Note</em> aus; sie steht damit direkt im Protokoll-PDF.
                </div>

                <div style="margin-bottom:11px;">
                    <strong style="color:#1b5e20;">2. Noten automatisch einsammeln</strong>
                    <span style="display:inline-block; padding:1px 6px; border-radius:3px; background:#1b5e20; color:#fff; font-size:0.75em; font-weight:700; letter-spacing:0.5px; vertical-align:middle;">BETA</span><br>
                    Setze die Spalte <em>Note</em> auf <em>„automatisch einsammeln“</em> und wähle die Fachlehrkraft aus.
                    Sie bekommt eine E-Mail mit Link zur Noteneingabe und wird bei Bedarf automatisch erinnert. Sobald
                    alle eingesammelten Noten da sind, wirst du benachrichtigt und lädst das fertige PDF im Dashboard
                    herunter. Bis dahin gibt es hier kein PDF.
                </div>

                <div style="margin-bottom:11px;">
                    <strong style="color:#1d2327;">3. Noten im PDF per Hand eintragen</strong><br>
                    Der Papierweg: Spalte <em>Note</em> leer lassen, PDF erzeugen und die Noten im Umlaufverfahren
                    handschriftlich ergänzen. Alternativ später über „Bearbeiten“ im Dashboard nachtragen und ein
                    neues PDF ziehen.
                </div>

                <p style="margin:0; padding-top:9px; border-top:1px solid #dcdcde; color:#50575e;">
                    Die beiden Häkchen rechts (<em>WebUntis</em>, <em>vorher abgeschlossen</em>) gelten nur für selbst
                    eingetragene Noten. Bei eingesammelten Fächern bestätigt die Fachlehrkraft den WebUntis-Eintrag selbst.
                </p>
            </div>

            <div class="mh-grades-overlay-wrap">
            <div id="grades_overlay" class="mh-grades-overlay <?= $has_existing_grades ? 'mh-hidden' : '' ?>">
                <div class="mh-grades-overlay-box">
                    <p><strong>Selbst eintragen, einsammeln lassen oder auf Papier?</strong></p>
                    <p>Trage ein, was dir vorliegt, setze die übrigen Fächer auf „automatisch einsammeln“<br>
                    – oder lass die Spalte leer und ergänze die Noten später auf dem PDF.</p>
                    <button type="button" id="btn_show_grades">Fächer &amp; Noten bearbeiten</button>
                </div>
            </div>

            <table class="mh-subject-table">
                <thead>
                    <tr>
                        <th width="25%">Fach</th>
                        <th width="25%">Lehrkraft</th>
                        <th width="10%">Note</th>
                        <th width="20%" style="text-align:center;">Teilnoten in WebUntis eingetragen?</th>
                        <th width="20%" style="text-align:center;">Fach vorher abgeschlossen?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Grundgerüst: 12 Zeilen, bei gespeicherten Formularen ggf. mehr
                    for($i=0; $i<$subject_row_count; $i++):
                        $s = $form_data['subjects'][$i] ?? [];
                    ?>
                    <tr>
                        <td>
                            
                                <select name="subj_name[]" style="width:100%;">
                                    <option value="">-- Fach wählen --</option>
                                    <?php foreach($subjects_list as $sub): ?>
                                        <option value="<?= esc_attr($sub['short_name']) ?>" <?= selected($s['name'] ?? '', $sub['short_name']) ?>>
                                            <?= esc_html($sub['short_name']) ?> - <?= esc_html($sub['display_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            
                        </td>
                        <td>
                            <select name="subj_teacher[]">
                                <option value="">-- Lehrkraft --</option>
                                <?php if(!empty($teachers_list)): foreach($teachers_list as $t): ?>
                                    <option value="<?= esc_attr($t['name']) ?>" <?= selected($s['teacher'] ?? '', $t['name']) ?>>
                                        <?= esc_html($t['name']) ?> (<?= esc_html($t['long_name']) ?>)
                                    </option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                       <td>
						<select name="subj_grade[]" class="mh-grade-select mh-no-validate">
							<option value="">-</option>
							<?php foreach(['1','2','3','4','5','6','NB','NE'] as $n): ?>
								<option value="<?= $n ?>" <?= selected($s['grade'] ?? '', $n) ?>><?= $n ?></option>
							<?php endforeach; ?>
							<option value="<?= esc_attr($collect_marker) ?>" <?= selected(($s['collect'] ?? '0'), '1') ?>>automatisch einsammeln</option>
						</select>
					</td>
                        <td>
                            <input type="checkbox" name="subj_webuntis[<?= $i ?>]" value="1" <?= (isset($s['webuntis']) && $s['webuntis'] == '1') ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <input type="checkbox" name="subj_completed[<?= $i ?>]" value="1" <?= (isset($s['completed']) && $s['completed'] == '1') ? 'checked' : '' ?>>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
				
            </table>
			<p style="margin-top:-10px;font-size:9pt;">NB = nicht bewertbar | NE = nicht erteilt</p>
			</div>
        </div>
            <div class="mh-input-group"><label>Beschlussfassung / Bemerkungen:<span class="mh-info-icon" data-tooltip="Sollten Fächer mit NB bewertet werden, brauchen wir auf jeden Fall eine Bemerkung.">?</span></label><textarea name="prot_remarks" style="width:100%; height:80px;"><?= $val('prot_remarks') ?></textarea></div>            
        </div>
		
		<div style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
			<div class="radio-group">
				<input type="checkbox" name="notice_accepted" value="1" id="chk_notice" required <?= $chk('notice_accepted', '1') ?>>
				<label for="chk_notice" style="font-weight:bold;">
					Ich habe zur Kenntnis genommen, dass dieses Formular inkl. aller Daten gespeichert wird und zu einem späteren Zeitpunkt weiter bearbeitet bzw. korrigiert werden kann. <span class="req">*</span>
				</label>
			</div>
		</div>
		
        <div class="btn-group">
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular nur prüfen</button>
            <button type="submit" name="submit_mode" value="collect" id="btn_collect" class="button button-secondary button-large" style="background:#1b5e20 !important; color:#fff !important; border-color:#1b5e20 !important; display:none;">
                Noteneinsammlung starten <span id="btn_collect_count"></span>
                <span style="display:inline-block; margin-left:6px; padding:1px 6px; border-radius:3px; background:#fff; color:#1b5e20; font-size:0.7em; font-weight:700; letter-spacing:0.5px; vertical-align:middle;">BETA</span>
            </button>
        </div>
        <p id="collect_hint" style="font-size:0.9em; color:#555; margin-top:10px; display:none;">
            <strong>Noten automatisch einsammeln <span style="color:#1b5e20;">(BETA)</span>:</strong> Dieses Verfahren ist
            neu und wird noch erprobt. Die betroffenen Fachlehrer*innen erhalten eine E-Mail mit Link zur Noteneingabe und
            werden bei Bedarf automatisch erinnert; in keiner Mail steht eine Note. Sobald alle eingesammelten Noten
            vorliegen, wirst du benachrichtigt und lädst das fertige Formular im Dashboard herunter. Bis dahin gibt es
            hier kein PDF. Für jedes eingesammelte Fach muss eine Lehrkraft ausgewählt sein, zu der sich eine
            E-Mail-Adresse auflösen lässt. Wenn etwas klemmt, sag Bescheid – und sammle im Zweifel auf Papier.
        </p>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. ELEMENT-REFERENZEN
    const classSelect = document.getElementById('mh_class_select');
    const studentSelect = document.getElementById('mh_student_select');
    const perspectiveSection = document.getElementById('section_perspective');
    const isFulltimeInput = document.getElementById('is_fulltime_class');
    const classHidden = document.getElementById('class_name_hidden');
    const displayClass = document.getElementById('display_classname');
    
    const f_last = document.getElementById('display_lastname');
    const f_first = document.getElementById('display_firstname');
    const f_dob = document.getElementById('field_dob');
    
    const h_last = document.getElementById('student_lastname');
    const h_first = document.getElementById('student_firstname');

    // 2. HELPER: SCHÜLER PER AJAX LADEN
    function fetchStudents(classId, selectedStudentId = null) {
        if (!classId) { 
            studentSelect.disabled = true; 
            studentSelect.innerHTML = '<option value="">-- Erst Klasse wählen --</option>'; 
            return; 
        }

        studentSelect.disabled = false; 
        studentSelect.innerHTML = `
            <option value="">-- Schüler*in wählen --</option>
            <option value="manual" ${selectedStudentId === 'manual' ? 'selected' : ''}>-- Manueller Eintrag (Schüler*in nicht in Liste) --</option>
        `;

        if (!selectedStudentId) {
            const loadingOpt = document.createElement('option');
            loadingOpt.text = 'Lade Klassenliste...';
            studentSelect.add(loadingOpt);
        }

        const formData = new FormData();
        formData.append('action', 'mh_get_students');
        formData.append('class_id', classId);
        formData.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            studentSelect.innerHTML = `
                <option value="">-- Schüler*in wählen --</option>
                <option value="manual" ${selectedStudentId === 'manual' ? 'selected' : ''}>-- Manueller Eintrag (Schüler*in nicht in Liste) --</option>
            `;
            if (data.success && data.data) {
                data.data.forEach(s => {
                    const isSelected = (selectedStudentId && s.wu_id == selectedStudentId) ? 'selected' : '';
                    studentSelect.innerHTML += `<option value="${s.wu_id}" data-last="${s.name}" data-first="${s.fore_name}" data-dob="${s.dob || ''}" data-schild="${s.schild_id || ''}" data-track="${s.track_key || ''}" ${isSelected}>${s.name}, ${s.fore_name}</option>`;
                });
            }
        }).catch(err => console.error("Fehler:", err));
    }

    // 3. HELPER: VOLLZEIT/TEILZEIT UI
    function updatePerspectiveUI() {
        const opt = classSelect.options[classSelect.selectedIndex];
        const protTypeInput = document.getElementById('input_prot_type');
        if (!opt || !opt.value) return;

        const isFulltime = opt.dataset.fulltime === "1";
        classHidden.value = opt.dataset.name || '';
        if(displayClass) displayClass.value = opt.dataset.name || '';

        if (isFulltime) {
            perspectiveSection.style.opacity = "1"; perspectiveSection.style.pointerEvents = "auto";
            isFulltimeInput.value = "1";
            if(protTypeInput) protTypeInput.value = "vollzeit";
            document.getElementById('perspective_req').style.display = "inline";
            perspectiveSection.querySelectorAll('input').forEach(i => i.disabled = false);
        } else {
            perspectiveSection.style.opacity = "0.4"; perspectiveSection.style.pointerEvents = "none";
            isFulltimeInput.value = "0";
            if(protTypeInput) protTypeInput.value = "berufsschule";
            document.getElementById('perspective_req').style.display = "none";
            perspectiveSection.querySelectorAll('input').forEach(i => { i.disabled = true; i.required = false; });
        }
    }

    // 4. MANUELLE EINGABE SYNCHRONISIEREN (Wichtig für deinen Fehler!)
    // Wenn der User tippt, kopieren wir den Wert in das versteckte Feld für PHP
    f_last.addEventListener('input', function() {
        if (studentSelect.value === 'manual') h_last.value = this.value;
    });
    f_first.addEventListener('input', function() {
        if (studentSelect.value === 'manual') h_first.value = this.value;
    });
    f_dob.addEventListener('input', function() {
        if (studentSelect.value === 'manual') calcAge();
    });

    // 5. CHANGE LISTENERS

    // Bildungsgang der gewählten Klasse. Die Stundentafel hängt am Bildungsgang und ist
    // für alle Schüler einer Klasse dieselbe - deshalb kommt der Schlüssel von der Klasse
    // und nicht vom einzelnen Schüler. Nur so bekommt auch ein manuell eingetragener
    // Schüler seine Fächer. Individuell bleiben allein die Kursbelegungen (schild_id).
    let currentClassTrack = '';

    function readClassTrack() {
        const opt = classSelect.options[classSelect.selectedIndex];
        currentClassTrack = (opt && opt.dataset.track) ? opt.dataset.track : '';
    }

    classSelect.addEventListener('change', function() {
        readClassTrack();
        fetchStudents(this.value);
        updatePerspectiveUI();
    });

    studentSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const isManual = this.value === 'manual';
        
        if (isManual) {
            f_last.value = ''; f_first.value = ''; f_dob.value = '';
            f_last.readOnly = false; f_first.readOnly = false; f_dob.readOnly = false;
            f_last.style.backgroundColor = '#fff'; f_first.style.backgroundColor = '#fff'; f_dob.style.backgroundColor = '#fff';
            h_last.value = ''; h_first.value = '';
            // Kein Schild-Datensatz, also keine Kursbelegungen - die Stundentafel der
            // Klasse gibt es aber trotzdem.
            fetchSubjectRows(currentClassTrack, '');
        } else if (this.value !== '') {
            f_last.value = opt.dataset.last || '';
            f_first.value = opt.dataset.first || '';
            f_dob.value = opt.dataset.dob || '';
            f_last.readOnly = true; f_first.readOnly = true; f_dob.readOnly = true;
            f_last.style.backgroundColor = '#e9e9e9'; f_first.style.backgroundColor = '#e9e9e9'; f_dob.style.backgroundColor = '#e9e9e9';
            h_last.value = f_last.value; h_first.value = f_first.value;
            calcAge();
            // Fächer aus Stundentafel des Bildungsgangs + Kursbelegungen vorbelegen.
            // Die Klasse gewinnt; der Schülerwert greift nur, solange die Klasse noch
            // keinen Bildungsgang zugeordnet hat.
            fetchSubjectRows(currentClassTrack || opt.dataset.track || '', opt.dataset.schild || '');
        }
    });

    // 6. INITIALISIERUNG (Edit-Modus)
    const initialClassId = classSelect.value;
    const initialStudentId = "<?= $val('student_wu_id') ?>";
    readClassTrack();
    if (initialClassId) {
        fetchStudents(initialClassId, initialStudentId);
        updatePerspectiveUI();
        if (initialStudentId === 'manual') {
            setTimeout(() => { studentSelect.dispatchEvent(new Event('change')); }, 200);
        }
    }

    // 7. ALTER & DATUM SYNC
    const dobInput = document.getElementById('field_dob');
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');
    function calcAge() {
        if(!dobInput.value) return;
        const dob = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) { age--; }
        let outputHtml = '';
        if (age < 18) { outputHtml = '<b style="color:#d63638">Minderjährig</b> (' + age + ')'; statusInput.value = '1'; } 
        else { 
            let schoolYearStart = today.getFullYear();
            if (today.getMonth() < 7) schoolYearStart--;
            let ageAtStart = schoolYearStart - dob.getFullYear();
            if (7 < dob.getMonth() || (7 === dob.getMonth() && 1 < dob.getDate())) ageAtStart--;
            outputHtml = '<b style="color:#46b450">Volljährig</b> (' + age + ')';
            outputHtml += ageAtStart >= 18 ? '<br><small>(Schuljahresbeginn volljährig)</small>' : '<br><small>(Schuljahresbeginn <u style="color:#d63638">nicht</u> volljährig)</small>';
            statusInput.value = '0';
        }
        statusDisplay.innerHTML = outputHtml;
    }
    if(dobInput) { dobInput.addEventListener('change', calcAge); if(dobInput.value) calcAge(); }

    const dateOffInput = document.getElementById('field_date_off'); 
    const protDateInput = document.getElementById('field_prot_date'); 
    const protIssueInput = document.getElementById('field_prot_issue_date'); 
    if(dateOffInput && protDateInput) {
        if(dateOffInput.value && !protDateInput.value) { protDateInput.value = dateOffInput.value; protIssueInput.value = dateOffInput.value; }
        dateOffInput.addEventListener('change', function() { protDateInput.value = this.value; protIssueInput.value = this.value; });
    }

    // 8. ALLGEMEINE TOGGLES
    const triggers = document.querySelectorAll('.toggle-trigger');
    const allTargets = document.querySelectorAll('.toggle-target');
    function updateToggles() {
        let activeTargetIds = new Set();
        triggers.forEach(tr => { if(tr.checked && tr.dataset.target) activeTargetIds.add(tr.dataset.target); });
        allTargets.forEach(t => {
            const isActive = activeTargetIds.has(t.id);
            const parentTarget = t.parentElement.closest('.toggle-target');
            const isParentInactive = parentTarget && (parentTarget.style.opacity === '0.4' || parentTarget.classList.contains('mh-hidden'));
            if (!isActive || isParentInactive) {
                if (t.classList.contains('mh-collapsible-section')) t.classList.add('mh-hidden');
                else { t.style.opacity = "0.4"; t.style.pointerEvents = "none"; }
                t.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = true; i.required = false; });
            } else {
                t.classList.remove('mh-hidden'); t.style.opacity = "1"; t.style.pointerEvents = "auto";
                t.querySelectorAll('input, select, textarea').forEach(i => {
                    if (i.closest('.toggle-target').id === t.id) {
                        i.disabled = false;
                        if (i.type !== 'hidden' && i.type !== 'checkbox' && i.tagName !== 'TEXTAREA' && !i.closest('.mh-subject-table') && !i.classList.contains('mh-no-validate')) { i.required = true; }
                    }
                });
            }
        });
    }
    triggers.forEach(r => r.addEventListener('change', updateToggles));
    setTimeout(() => { updateToggles(); updateProtocolMode(); }, 100);

    // 8b. PROTOKOLL-MODUS
    // Liegt ein bestehendes Protokoll bei, gibt es hier nichts einzusammeln - die Noten
    // stehen im beigefügten Protokoll. Der Button würde sonst einen Umlauf starten,
    // dessen Ergebnis niemand braucht.
    const protModeCreate   = document.getElementById('prot_mode_create');
    const protModeExisting = document.getElementById('prot_mode_existing');
    const protExistingHint = document.getElementById('prot_existing_hint');
    const collectBtn       = document.querySelector('button[name="submit_mode"][value="collect"]');
    const collectHint      = document.getElementById('collect_hint');

    // Der Knopf zur Noteneinsammlung erscheint nur, wenn es tatsächlich etwas
    // einzusammeln gibt - also mindestens eine Fächerzeile auf "per Mail anfragen"
    // steht. So kann es keinen Widerspruch zwischen Knopf und Tabelle geben, und der
    // Knopf erklärt sich aus der Tabelle heraus.
    const COLLECT_MARKER = <?= json_encode($collect_marker) ?>;
    const countLabel     = document.getElementById('btn_collect_count');

    function countCollectRows() {
        let n = 0;
        document.querySelectorAll('select[name="subj_grade[]"]').forEach(sel => {
            if (sel.value === COLLECT_MARKER && !sel.disabled) n++;
        });
        return n;
    }

    // Die beiden Häkchen gehören zur selbst eingetragenen Note. Wird ein Fach
    // eingesammelt, bestätigt die Fachlehrkraft den WebUntis-Eintrag im eigenen
    // Formular - dann darf hier nichts stehen, was sie gleich überschreibt.
    function syncRowChecks() {
        document.querySelectorAll('select[name="subj_grade[]"]').forEach(sel => {
            const row = sel.closest('tr');
            if (!row) return;
            const isCollect = sel.value === COLLECT_MARKER;
            row.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                if (isCollect) {
                    cb.checked  = false;
                    cb.disabled = true;
                    cb.title    = 'Wird von der Fachlehrkraft beim Eintragen der Note bestätigt.';
                } else {
                    cb.disabled = false;
                    cb.title    = '';
                }
                cb.parentElement.style.opacity = isCollect ? '0.35' : '1';
            });
        });
    }

    function updateProtocolMode() {
        const existing = protModeExisting && protModeExisting.checked;
        const n        = existing ? 0 : countCollectRows();
        const show     = n > 0;

        if (protExistingHint) protExistingHint.style.display = existing ? 'block' : 'none';
        if (collectBtn)  collectBtn.style.display  = show ? '' : 'none';
        if (collectHint) collectHint.style.display = show ? '' : 'none';
        if (countLabel)  countLabel.textContent    = show ? '(' + n + (n === 1 ? ' Fach)' : ' Fächer)') : '';

        // Muss NACH updateToggles() laufen: das schaltet beim Aufklappen des
        // Protokollbereichs pauschal alle Felder wieder frei.
        syncRowChecks();
    }

    if (protModeCreate)   protModeCreate.addEventListener('change', updateProtocolMode);
    if (protModeExisting) protModeExisting.addEventListener('change', updateProtocolMode);

    // Auch auf später per Vorbelegung angehängte Zeilen reagieren: ein Listener am
    // Container statt einer pro Select.
    const subjTable = document.querySelector('.mh-subject-table');
    if (subjTable) subjTable.addEventListener('change', updateProtocolMode);

    updateProtocolMode();
	// Logik für NB -> Bemerkungspflicht
    const remarksField = document.querySelector('textarea[name="prot_remarks"]');

    function checkNBRequirement() {
        let nbFound = false;
        // Frisch abfragen: die Vorbelegung kann Zeilen nachträglich angehängt haben.
        document.querySelectorAll('.mh-grade-select').forEach(select => {
            if (select.value === 'NB') nbFound = true;
        });

        if (nbFound) {
            remarksField.required = true;
            remarksField.style.borderColor = '#d63638';
            remarksField.placeholder = 'Begründung für NB hier zwingend erforderlich...';
        } else {
            remarksField.required = false;
            remarksField.style.borderColor = '';
            remarksField.placeholder = '';
        }
    }

    // Delegation statt Einzel-Listener: die Vorbelegung kann Zeilen nachträglich anhängen.
    const subjectTableEl = document.querySelector('.mh-subject-table');
    if (subjectTableEl) {
        subjectTableEl.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('mh-grade-select')) checkNBRequirement();
        });
    }
    // Initialer Check beim Laden (für Edit-Modus)
    checkNBRequirement();

    // ---------------------------------------------------------------
    // FÄCHER-VORBELEGUNG (Stundentafel des Bildungsgangs + Kursbelegungen)
    // ---------------------------------------------------------------
    const subjectTbody = document.querySelector('.mh-subject-table tbody');
    // Stehen schon Fächer im Formular (Bearbeiten-Modus / Reload nach Fehler),
    // wird nicht vorbelegt — sonst gingen erfasste Noten verloren.
    const subjectsLocked = <?= $has_existing_subjects ? 'true' : 'false' ?>;

    function subjectRows() {
        return subjectTbody ? Array.from(subjectTbody.rows) : [];
    }

    // subj_name/subj_teacher/subj_grade laufen über [], die Checkboxen über feste
    // Indizes — das Model greift sie per Index ab. Beim Klonen müssen die Indizes
    // deshalb lückenlos weiterlaufen.
    function renumberRow(row, index) {
        const wu = row.querySelector('input[name^="subj_webuntis"]');
        const co = row.querySelector('input[name^="subj_completed"]');
        if (wu) wu.name = 'subj_webuntis[' + index + ']';
        if (co) co.name = 'subj_completed[' + index + ']';
    }

    function ensureRowCount(needed) {
        const rows = subjectRows();
        if (!subjectTbody || rows.length === 0 || rows.length >= needed) return;
        const blueprint = rows[rows.length - 1];
        for (let i = rows.length; i < needed; i++) {
            const clone = blueprint.cloneNode(true);
            clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
            clone.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            subjectTbody.appendChild(clone);
            renumberRow(clone, i);
        }
    }

    function buildSubjectOptions(select, trackOptions, otherOptions, selectedValue) {
        select.innerHTML = '';
        select.add(new Option('-- Fach wählen --', ''));

        if (trackOptions.length) {
            const g = document.createElement('optgroup');
            g.label = 'Fächer des Bildungsgangs';
            trackOptions.forEach(o => g.appendChild(new Option(o.label, o.value)));
            select.add(g);
        }
        if (otherOptions.length) {
            const g2 = document.createElement('optgroup');
            // Notausgang für Fachwechsler/Wiederholer: der Rest der Schild-Fächerliste
            // bleibt erreichbar, steht aber unterhalb der Bildungsgang-Fächer.
            g2.label = trackOptions.length ? 'Weitere Fächer' : 'Alle Fächer';
            otherOptions.forEach(o => g2.appendChild(new Option(o.label, o.value)));
            select.add(g2);
        }
        if (selectedValue) {
            // Kursbezeichnungen stehen in keiner Fächerliste — Option ergänzen.
            if (!Array.from(select.options).some(o => o.value === selectedValue)) {
                select.add(new Option(selectedValue, selectedValue));
            }
            select.value = selectedValue;
        }
    }

    function fillSubjectRows(payload) {
        if (!subjectTbody || subjectsLocked) return;

        const rowsData     = payload.rows || [];
        const trackOptions = payload.track_options || [];
        const otherOptions = payload.other_options || [];

        ensureRowCount(Math.max(rowsData.length, subjectRows().length));

        subjectRows().forEach((row, i) => {
            const nameSel  = row.querySelector('select[name="subj_name[]"]');
            const teachSel = row.querySelector('select[name="subj_teacher[]"]');
            const gradeSel = row.querySelector('select[name="subj_grade[]"]');
            const data     = rowsData[i];

            if (nameSel) buildSubjectOptions(nameSel, trackOptions, otherOptions, data ? data.value : '');
            if (gradeSel) gradeSel.value = '';
            if (teachSel) {
                // Lehrkraft nur bei Kursen: die Stundentafel kennt keine Fachlehrer.
                const wanted = (data && data.teacher) ? data.teacher : '';
                teachSel.value = Array.from(teachSel.options).some(o => o.value === wanted) ? wanted : '';
            }
            row.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            renumberRow(row, i);
        });

        // Neu angehängte Zeilen müssen denselben Aktiv/Inaktiv-Zustand bekommen
        // wie der Rest des Protokollbereichs.
        if (typeof updateToggles === 'function') updateToggles();
        if (typeof updateProtocolMode === 'function') updateProtocolMode();
        checkNBRequirement();
    }

    function fetchSubjectRows(trackKey, schildId) {
        if (!subjectTbody || subjectsLocked) return;
        if (!trackKey && !schildId) return;

        const fd = new FormData();
        fd.append('action', 'mh_get_subject_rows');
        fd.append('track_key', trackKey || '');
        fd.append('schild_id', schildId || '');
        fd.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success && data.data) fillSubjectRows(data.data); })
        .catch(err => console.error("Fehler bei der Fächer-Vorbelegung:", err));
    }

    // Überblendung Notensammlung: erst nach Klick Noteneingabe freigeben
    const gradesOverlay = document.getElementById('grades_overlay');
    const btnShowGrades = document.getElementById('btn_show_grades');
    if (btnShowGrades && gradesOverlay) {
        btnShowGrades.addEventListener('click', function() {
            gradesOverlay.classList.add('mh-hidden');
        });
    }

});
</script>