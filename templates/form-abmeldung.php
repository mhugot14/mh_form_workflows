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
						<option value="">-- Bitte wählen --</option>
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
                    <input type="date" name="dob" id="field_dob" required class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Klasse (Anzeige)</label><input type="text" id="display_classname" readonly value="<?= $val('class_name') ?>"></div>
                <div class="mh-input-group">
                    <label>Klassenlehrer/in (angemeldet) <span class="req">*</span></label>
                    <input type="text" name="teacher" required readonly value="<?= $val('teacher') ?: $teacher_default ?>">
                </div>  
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Status <span class="mh-info-icon" data-tooltip="Ermittelt Volljährigkeit zum Stichtag 01.08.">?</span></label><div class="mh-fake-input"><span id="status_display">...</span><input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>"></div></div>
                <div class="mh-input-group"><label>Datum der Abmeldung <span class="req">*</span></label><input type="date" name="date_off" id="field_date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>"></div>
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
        </div>
		
        <!-- SEKTION: ANSCHLUSSPERSPEKTIVE (Bedingt Pflicht) -->
        <div id="section_perspective" class="mh-form-section">
            <h4 style="margin-bottom:5px;">Anschlussperspektive <span class="req" id="perspective_req">*</span></h4>
            <p style="font-size:0.85em; color:#666; margin-bottom:15px;">Auszufüllen für Vollzeit-Bildungsgänge.</p>
            
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
            <div class="radio-group"><input type="radio" name="certificate" value="ueberweisung" id="z_ue" required <?= $chk('certificate', 'ueberweisung') ?>> <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small>(Wechsel innerhalb Schulstufe)</small></label></div>

            <div style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px;">
                <div class="radio-group">
                    <input type="checkbox" name="protocol_attached" value="1" id="chk_protocol" class="toggle-trigger" data-target="protocol_wrapper" <?php echo ( empty($form_data) || (isset($form_data['protocol_attached']) && $form_data['protocol_attached'] == '1') ) ? 'checked' : ''; ?>>
                    <label for="chk_protocol" style="font-weight:bold;">Zeugniskonferenzprotokoll beifügen</label>
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
                    <label>Konferenzdatum <span class="req">*</span></label>
                    <input type="date" name="prot_date" id="field_prot_date" readonly value="<?= $val('prot_date') ?>" style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>">
                    <?php if ( ! empty( $form_data['prot_was_corrected'] ) ): ?><div style="font-size:0.8em; color:#b7791f; margin-top:3px; font-weight:bold;">ℹ️ Korrigiert auf Schultag.</div><?php endif; ?>
                </div>
                <div class="mh-input-group"><label>Ausgabedatum <span class="req">*</span></label><input type="date" name="prot_issue_date" id="field_prot_issue_date" readonly value="<?= $val('prot_issue_date') ?>" style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>"></div>
                <div class="mh-input-group"><label>Vorsitzende/r <span class="req">*</span></label><input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>"></div>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                 <div class="mh-input-group"><label>Raum <span class="req">*</span></label><input type="text" name="prot_room" value="<?= $val('prot_room') ?>"></div>
                 <div class="mh-grid-row mh-grid-2" style="margin-bottom:0 !important; gap: 10px !important;">
                    <div class="mh-input-group"><label>Fehlstunden <span class="req">*</span></label><input type="number" name="missed_hours" value="<?= $val('missed_hours') ?>"></div>
                    <div class="mh-input-group"><label>Unentschuldigt <span class="req">*</span></label><input type="number" name="missed_ue" value="<?= $val('missed_ue') ?>"></div>
                 </div>
            </div>
<!-- SEKTION: FÄCHER & NOTEN -->
        <div style="margin-top: 25px; margin-bottom: 20px;">
            <h5 style="margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Fächer & Noten (Vorausfüllung für Protokoll)</h5>
            
            <table class="mh-subject-table">
                <thead>
                    <tr>
                        <th width="30%">Fach</th>
                        <th width="25%">Lehrkraft</th>
                        <th width="10%">Note</th>
                        <th width="15%" style="text-align:center;">WebUntis?</th>
                        <th width="20%" style="text-align:center;">Fach vorher abgeschlossen?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Wir zeigen 12 Zeilen an
                    for($i=0; $i<12; $i++): 
                        $s = $form_data['subjects'][$i] ?? [];
                    ?>
                    <tr>
                        <td>
                            <!-- Platzhalter entfernt -->
                            <input type="text" name="subj_name[]" value="<?= esc_attr($s['name'] ?? '') ?>">
                        </td>
                        <td>
                            <select name="subj_teacher[]">
                                <option value="">-- Lehrer --</option>
                                <?php if(!empty($teachers_list)): foreach($teachers_list as $t): ?>
                                    <option value="<?= esc_attr($t['name']) ?>" <?= selected($s['teacher'] ?? '', $t['name']) ?>>
                                        <?= esc_html($t['name']) ?> (<?= esc_html($t['long_name']) ?>)
                                    </option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="subj_grade[]" value="<?= esc_attr($s['grade'] ?? '') ?>">
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
        </div>
            <div class="mh-input-group"><label>Beschlussfassung / Bemerkungen:</label><textarea name="prot_remarks" style="width:100%; height:80px;"><?= $val('prot_remarks') ?></textarea></div>            
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular nur prüfen</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('mh_class_select');
    const studentSelect = document.getElementById('mh_student_select');
    const perspectiveSection = document.getElementById('section_perspective');
    const isFulltimeInput = document.getElementById('is_fulltime_class');
    const classHidden = document.getElementById('class_name_hidden');
    const displayClass = document.getElementById('display_classname');

    // --- 1. FUNKTION: SCHÜLER PER AJAX LADEN ---
    function fetchStudents(classId, selectedStudentId = null) {
        if (!classId) {
            studentSelect.disabled = true;
            studentSelect.innerHTML = '<option value="">-- Erst Klasse wählen --</option>';
            return;
        }

        studentSelect.disabled = true;
        // Nur "Lade..." anzeigen, wenn wir nicht gerade einen bestehenden Schüler wiederherstellen
        if (!selectedStudentId) {
            studentSelect.innerHTML = '<option>Lade Schüler...</option>';
        }

        const formData = new FormData();
        formData.append('action', 'mh_get_students');
        formData.append('class_id', classId);
        formData.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                studentSelect.innerHTML = '<option value="">-- Schüler wählen --</option>';
                data.data.forEach(s => {
                    // Prüfen, ob dieser Schüler vorselektiert werden muss (beim Bearbeiten)
                    const isSelected = (selectedStudentId && s.wu_id == selectedStudentId) ? 'selected' : '';
                     studentSelect.innerHTML += `<option value="${s.wu_id}" data-last="${s.name}" data-first="${s.fore_name}" data-dob="${s.dob || ''}" ${isSelected}>${s.name}, ${s.fore_name}</option>`;
                });
                studentSelect.disabled = false;
            }
        });
    }

    // --- 2. FUNKTION: VOLLZEIT/TEILZEIT UI STEUERN ---
    function updatePerspectiveUI() {
        const opt = classSelect.options[classSelect.selectedIndex];
        const protTypeInput = document.getElementById('input_prot_type'); // Das neue hidden field

        if (!opt || !opt.value) {
            perspectiveSection.style.opacity = "0.4";
            perspectiveSection.style.pointerEvents = "none";
            return;
        }

        const isFulltime = opt.dataset.fulltime === "1";
        classHidden.value = opt.dataset.name || '';
        if(displayClass) displayClass.value = opt.dataset.name || '';

        if (isFulltime) {
            // --- VOLLZEIT ---
            perspectiveSection.style.opacity = "1";
            perspectiveSection.style.pointerEvents = "auto";
            isFulltimeInput.value = "1";
            if(protTypeInput) protTypeInput.value = "vollzeit"; // Automatisch setzen
            
            document.getElementById('perspective_req').style.display = "inline";
            perspectiveSection.querySelectorAll('input').forEach(i => i.disabled = false);
        } else {
            // --- TEILZEIT ---
            perspectiveSection.style.opacity = "0.4";
            perspectiveSection.style.pointerEvents = "none";
            isFulltimeInput.value = "0";
            if(protTypeInput) protTypeInput.value = "berufsschule"; // Automatisch setzen
            
            document.getElementById('perspective_req').style.display = "none";
            perspectiveSection.querySelectorAll('input').forEach(i => {
                i.disabled = true;
                i.required = false;
            });
        }
    }

    // --- 3. EVENT LISTENER ---

    // Klassenwahl geändert
    classSelect.addEventListener('change', function() {
        fetchStudents(this.value);
        updatePerspectiveUI();
    });

    // Schülerwahl geändert -> Namen in Hidden Fields & Anzeige schreiben
    studentSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const last = opt.dataset.last || '';
        const first = opt.dataset.first || '';
		const dob = opt.dataset.dob || ''; // NEU
        document.getElementById('student_lastname').value = last;
        document.getElementById('student_firstname').value = first;
        document.getElementById('display_lastname').value = last;
        document.getElementById('display_firstname').value = first;
		// NEU: Geburtsdatum setzen
    const dobInput = document.getElementById('field_dob');
    if (dob) {
        dobInput.value = dob;
        // WICHTIG: Altersberechnung neu anstoßen!
        calcAge(); 
    }
    });

    // --- 4. INITIALISIERUNG BEIM LADEN (WICHTIG FÜR EDIT) ---
    const initialClassId = classSelect.value;
    const initialStudentId = "<?= $val('student_wu_id') ?>";

    if (initialClassId) {
        // Wenn eine Klasse geladen wurde (Edit-Modus), Schülerliste holen
        fetchStudents(initialClassId, initialStudentId);
        updatePerspectiveUI();
    }

    // --- 5. RESTLICHE LOGIK (Alter, Sync Dates, Toggles) ---
    
    // Alter berechnen
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
            let schoolYearStartYear = today.getFullYear();
            if (today.getMonth() < 7) { schoolYearStartYear--; }
            let ageAtStart = schoolYearStartYear - dob.getFullYear();
            if (7 < dob.getMonth() || (7 === dob.getMonth() && 1 < dob.getDate())) { ageAtStart--; }
            outputHtml = '<b style="color:#46b450">Volljährig</b> (' + age + ')';
            outputHtml += ageAtStart >= 18 ? '<br><small>(Schuljahresbeginn volljährig)</small>' : '<br><small>(Schuljahresbeginn <u style="color:#d63638">nicht</u> volljährig)</small>';
            statusInput.value = '0';
        }
        statusDisplay.innerHTML = outputHtml;
    }
    if(dobInput) { dobInput.addEventListener('change', calcAge); if(dobInput.value) calcAge(); }

    // Datum Sync
    const dateOffInput = document.getElementById('field_date_off'); 
    const protDateInput = document.getElementById('field_prot_date'); 
    const protIssueInput = document.getElementById('field_prot_issue_date'); 
    if(dateOffInput && protDateInput) {
        if(dateOffInput.value && !protDateInput.value) { protDateInput.value = dateOffInput.value; protIssueInput.value = dateOffInput.value; }
        dateOffInput.addEventListener('change', function() { protDateInput.value = this.value; protIssueInput.value = this.value; });
    }

    // Allgemeine Toggles (z.B. Protokoll-Bereich)
    const triggers = document.querySelectorAll('.toggle-trigger');
    const allTargets = document.querySelectorAll('.toggle-target');

    function updateToggles() {
        let activeIds = new Set();
        triggers.forEach(tr => { if(tr.checked && tr.dataset.target) activeIds.add(tr.dataset.target); });
        
        allTargets.forEach(t => {
            const isActive = activeIds.has(t.id);
            const parent = t.parentElement.closest('.toggle-target');
            const isParentInactive = parent && (parent.style.opacity === '0.5' || parent.classList.contains('mh-hidden'));
            
            if(!isActive || isParentInactive) {
                // INAKTIV-Zustand
                if(t.classList.contains('mh-collapsible-section')) t.classList.add('mh-hidden');
                else { t.style.opacity = '0.5'; t.style.pointerEvents = 'none'; }
                t.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = true; i.required = false; });
            } else {
                // AKTIV-Zustand
                t.classList.remove('mh-hidden'); t.style.opacity = '1'; t.style.pointerEvents = 'auto';
                
                t.querySelectorAll('input, select, textarea').forEach(i => {
                    if(i.closest('.toggle-target').id === t.id) {
                        i.disabled = false;
                        
                        // --- LOGIK-KORREKTUR FÜR DIE TABELLE ---
                        // Wir prüfen, ob das Element innerhalb der Notentabelle liegt
                        const isInsideSubjectTable = i.closest('.mh-subject-table');
                        
                        if(
                            i.type !== 'hidden' && 
                            i.type !== 'checkbox' && 
                            i.tagName !== 'TEXTAREA' &&
                            !isInsideSubjectTable // <-- Wenn es in der Tabelle ist, NICHT required setzen
                        ) { 
                            i.required = true; 
                        }
                    }
                });
            }
        });
    }
    triggers.forEach(r => r.addEventListener('change', updateToggles));
    setTimeout(() => { updateToggles(); }, 100);
});
</script>