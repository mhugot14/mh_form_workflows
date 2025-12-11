<?php
// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';

// Extrahiere spezielle Warnung aus Errors
$warning_msg = '';
if ( isset( $form_errors['date_autocorrect'] ) ) {
    $warning_msg = $form_errors['date_autocorrect'];
    unset( $form_errors['date_autocorrect'] ); // Damit es unten nicht doppelt rot erscheint
}
?>

<style>
    /* CSS RESET & LAYOUT */
    .mh-form-wrapper { max-width: 900px; margin: 0 auto; box-sizing: border-box; font-family: inherit; }
    .mh-form-wrapper * { box-sizing: border-box !important; float: none !important; position: static !important; }
    
    /* Layout Sektionen */
    .mh-form-section { background: #f9f9f9; border: 1px solid #ccc; padding: 20px; margin-bottom: 25px; border-radius: 4px; width: 100% !important; display: block !important; }
    .mh-form-section h4 { margin-top: 0 !important; margin-bottom: 20px !important; border-bottom: 1px solid #ddd !important; padding-bottom: 10px; color: #333; }

    /* Grid */
    .mh-grid-row { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important; gap: 20px !important; margin-bottom: 15px !important; width: 100% !important; }
    .mh-grid-2 { grid-template-columns: 1fr 1fr !important; }
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr !important; }
    @media (max-width: 768px) { .mh-grid-2, .mh-grid-3 { grid-template-columns: 1fr !important; } }

    /* Input Groups */
    .mh-input-group { display: flex !important; flex-direction: column !important; width: 100% !important; margin: 0 !important; border: none !important; padding: 0 !important; }
    .mh-input-group label { display: block !important; width: 100% !important; margin: 0 0 5px 0 !important; font-weight: bold; line-height: 1.4 !important; height: auto !important; }
    
    /* Inputs */
    .mh-input-group input[type="text"], .mh-input-group input[type="date"], .mh-input-group input[type="number"], .mh-input-group select, .mh-input-group textarea {
        display: block !important; width: 100% !important; height: 40px !important; padding: 6px 12px !important; margin: 0 !important; border: 1px solid #aaa !important; background-color: #fff !important; border-radius: 4px !important; font-size: 15px !important; box-shadow: none !important;
    }
    .mh-input-group textarea { height: auto !important; }
    .mh-input-group input[readonly] { background-color: #e9e9e9 !important; color: #555 !important; cursor: not-allowed; }
    .mh-fake-input { display: flex !important; align-items: center; height: 40px; width: 100%; background: #e9e9e9; border: 1px solid #aaa; border-radius: 4px; padding: 0 10px; color: #555; }

    /* Radio/Checkbox */
    .radio-group { display: flex !important; flex-direction: row !important; align-items: flex-start !important; margin-bottom: 8px !important; gap: 10px !important; }
    .radio-group input { width: 18px !important; height: 18px !important; margin-top: 4px !important; flex-shrink: 0; }
    .radio-group label { font-weight: normal !important; margin: 0 !important; display: inline-block !important; }

    /* Messages */
    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .mh-warning-box { background: #fff8e5; border-left: 5px solid #e5a912; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
    
    /* Misc */
    .mh-sub-group { margin-left: 28px; padding: 15px; border-left: 3px solid #ddd; background: #fff; margin-bottom: 15px; margin-top: 5px; }
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    .mh-hidden { display: none !important; }
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 12px 24px !important; cursor: pointer; }
    
    /* Tooltip */
    .mh-info-icon { display: inline-block; width: 18px; height: 18px; background: #0073aa; color: #fff; border-radius: 50%; text-align: center; line-height: 18px; font-size: 12px; font-weight: bold; cursor: help; margin-left: 5px; position: relative !important; }
    .mh-info-icon:hover::after { content: attr(data-tooltip); position: absolute; bottom: 25px; left: -100px; width: 250px; padding: 10px; background: #333; color: #fff; font-size: 12px; font-weight: normal; line-height: 1.4; border-radius: 4px; z-index: 9999; }
    
    /* Theme Text Transform Override */
    .mh-form-wrapper input, .mh-form-wrapper select, .mh-form-wrapper label, .mh-form-wrapper span, .mh-form-wrapper div { text-transform: none !important; font-variant: normal !important; }
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box"><h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3></div>
    <?php endif; ?>

    <!-- WARNUNG (GELB) -->
    <?php if ( ! empty( $warning_msg ) ): ?>
        <div class="mh-warning-box">
             <h3 style="margin-top:0; color:#b7791f;">⚠️ Hinweis zur Datumsänderung:</h3>
             <div style="color: #8a6d3b; line-height: 1.4;"><?= $warning_msg ?></div>
        </div>
    <?php endif; ?>

    <!-- FEHLER (ROT) -->
    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">❌ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;"><?php foreach($form_errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- SEKTION 1: Stammdaten -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            <div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Nachname <span class="req">*</span></label><input type="text" name="lastname" required class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>"></div>
                <div class="mh-input-group"><label>Vorname <span class="req">*</span></label><input type="text" name="firstname" required class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>"></div>
                <div class="mh-input-group"><label>Geburtsdatum <span class="req">*</span></label><input type="date" name="dob" id="field_dob" required class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>"></div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Klasse <span class="req">*</span></label><input type="text" name="class_name" required class="<?= $err_cls('class_name') ?>" value="<?= $val('class_name') ?>"></div>
                <div class="mh-input-group"><label>Klassenlehrer/in (Kürzel) <span class="req">*</span></label><input type="text" name="teacher" required class="<?= $err_cls('teacher') ?>" value="<?= $val('teacher') ?>"></div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Status</label><div class="mh-fake-input"><span id="status_display">...</span><input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>"></div></div>
                <div class="mh-input-group"><label>Datum der Abmeldung / Kündigung <span class="req">*</span><span class="mh-info-icon" data-tooltip="Datum des Endes des Schulverhältnisses.">?</span></label><input type="date" name="date_off" id="field_date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>"></div>
            </div>
        </div>

        <!-- SEKTION 2: Grund -->
        <div class="mh-form-section">
            <h4>Grund der Abmeldung <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="reason" value="schulwechsel" id="r_wechsel" class="toggle-trigger" data-target="new_school_wrap" required <?= $chk('reason', 'schulwechsel') ?>> <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule)</label></div>
            <div id="new_school_wrap" class="mh-sub-group toggle-target"><div class="mh-input-group"><label>Name der Schule <span class="req">*</span></label><input type="text" name="new_school" placeholder="Schulname" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>"></div></div>
            <div class="radio-group"><input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> <label for="r_beschl">Ausschulung Beschluss Teillehrerkonferenz</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> <label for="r_abm">Abmeldung</label></div>
        </div>

        <!-- SEKTION 3: Schulpflicht -->
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
		
		<!-- NEUE SEKTION: LAUFBAHN / ANSCHLUSS -->
        <div class="mh-form-section">
            <h4 style="margin-bottom:10px;">Laufbahn / Anschluss <span class="req">*</span></h4>
            
            <div class="mh-input-group" style="margin:0 !important;">
                <label style="font-weight:normal; margin-bottom:10px;">
                    Die weitere Laufbahn des Schülers / der Schülerin ist gesichert (Anschluss)?
                </label>
                
                <div style="display:flex; gap: 30px;">
                    <div class="radio-group">
                        <input type="radio" name="future_secured" value="yes" id="fs_yes" required <?= $chk('future_secured', 'yes') ?>> 
                        <label for="fs_yes"><b>Ja</b></label>
                    </div>
                    
                    <div class="radio-group">
                        <input type="radio" name="future_secured" value="no" id="fs_no" <?= $chk('future_secured', 'no') ?>> 
                        <label for="fs_no"><b>Nein</b></label>
                    </div>
                </div>
				<div><small>Ist dies nicht der Fall, wird der Name zur Nachverfolgung an die Agentur für Arbeit weitergegeben.</small></div>
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis -->
        <div class="mh-form-section">
            <h4>Zeugnis</h4>
            <div class="radio-group"><input type="radio" name="certificate" value="abgang" id="z_ab" class="toggle-trigger" <?= $chk('certificate', 'abgang') ?>> <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <small>(Ohne Abschluss)</small></label></div>
            <div class="radio-group"><input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="toggle-trigger" data-target="missed_wrapper" <?= $chk('certificate', 'ueberweisung') ?>> <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small>(Wechsel innerhalb Schulstufe)</small></label></div>
            <div id="missed_wrapper" class="mh-grid-row mh-grid-2 toggle-target mh-sub-group">
                <div class="mh-input-group"><label>Fehlstunden Gesamt <span class="req">*</span></label><input type="number" name="missed_hours" value="<?= $val('missed_hours') ?>"></div>
                <div class="mh-input-group"><label>Unentschuldigt <span class="req">*</span></label><input type="number" name="missed_ue" value="<?= $val('missed_ue') ?>"></div>
            </div>
            <div style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px;">
                <div class="radio-group"><input type="checkbox" name="protocol_attached" value="1" id="chk_protocol" class="toggle-trigger" data-target="protocol_wrapper" <?= $chk('protocol_attached', '1') ?>> <label for="chk_protocol" style="font-weight:bold;">Zeugniskonferenzprotokoll beifügen</label></div>
            </div>
        </div>

        <!-- SEKTION 5: Protokoll (Optimiert & Gelb Markiert) -->
        <div id="protocol_wrapper" class="mh-form-section toggle-target mh-collapsible-section" style="border-left: 5px solid #0073aa;">
            <h4>Angaben zum Konferenzprotokoll</h4>
            <div class="radio-group"><input type="radio" name="prot_type" value="berufsschule" id="pt_bs" class="toggle-trigger" data-target="prot_bs_fields" <?= $chk('prot_type', 'berufsschule') ?>> <label><b>Teilzeit</b> (u.a. Berufsschule)</label></div>
            <div class="radio-group"><input type="radio" name="prot_type" value="vollzeit" id="pt_vz" class="toggle-trigger" data-target="prot_vz_fields" <?= $chk('prot_type', 'vollzeit') ?>> <label><b>Vollzeit</b></label></div>

            <div class="mh-grid-row mh-grid-3" style="margin-top:20px;">
                
                <!-- DATUM MIT MARKIERUNG -->
                <div class="mh-input-group">
                    <label>Konferenzdatum<span class="req">*</span></label>
                    <input type="date" name="prot_date" id="field_prot_date" readonly 
                           value="<?= $val('prot_date') ?>"
                           style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>">
                    <?php if ( ! empty( $form_data['prot_was_corrected'] ) ): ?>
                        <div style="font-size:0.8em; color:#b7791f; margin-top:3px; font-weight:bold;">
                            ℹ️ Korrigiert auf Schultag.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mh-input-group">
                    <label>Ausgabedatum<span class="req">*</span></label>
                    <input type="date" name="prot_issue_date" id="field_prot_issue_date" readonly 
                           value="<?= $val('prot_issue_date') ?>"
                           style="<?= !empty($form_data['prot_was_corrected']) ? 'border: 2px solid #e5a912; background-color:#fff8e5;' : '' ?>">
                </div>

                <div class="mh-input-group"><label>Vorsitzende/r<span class="req">*</span></label><input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>"></div>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                 <div class="mh-input-group"><label>Raum<span class="req">*</span></label><input type="text" name="prot_room" value="<?= $val('prot_room') ?>"></div>
                 <div></div> 
            </div>
            <div class="mh-input-group"><label>Beschlussfassung / Bemerkungen:</label><textarea name="prot_remarks" style="width:100%;"><?= $val('prot_remarks') ?></textarea></div>

            <div id="prot_vz_fields" class="mh-sub-group toggle-target">
				<h5>Angaben zur Vollzeit-Ausschulung</h5>
                <div class="mh-grid-row mh-grid-2">
                     <div class="mh-input-group"><label>Ende Schulverhältnis:</label><input type="date" name="prot_end_school" value="<?= $val('prot_end_school') ?>"></div>
                     <div class="mh-input-group"><div class="radio-group"><input type="checkbox" name="prot_check_compulsory" value="1" <?= $chk('prot_check_comp', '1') ?>> <label>Schulpflicht überprüft?</label></div></div>
                </div>
                 <div class="mh-input-group"><label>Überwiesen an Schule:</label><input type="text" name="prot_transfer" value="<?= $val('prot_transfer') ?>"></div>
            </div>
            
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular prüfen</button>
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ALTER RECHNER
    const dobInput = document.getElementById('field_dob');
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');
function calcAge() {
        if(!dobInput.value) return;
        
        const dob = new Date(dobInput.value);
        const today = new Date(); // Oder 'field_date_off', falls Stichtag das Abmeldedatum sein soll
        
        // 1. Tatsächliches Alter HEUTE berechnen
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        let outputHtml = '';
        let isMinor = 0;

        if (age < 18) {
            // Fall: Generell minderjährig
            outputHtml = '<span style="color:#d63638; font-weight:bold;">Minderjährig</span> (' + age + ')';
            isMinor = 1;
        } else {
            // Fall: Heute Volljährig -> Prüfung auf Schuljahresbeginn (01.08.)
            
            // Ermittlung des relevanten Schuljahresbeginns (01.08.)
            let schoolStartYear = today.getFullYear();
            // Wenn wir aktuell VOR August sind (Jan-Juli), begann das Schuljahr letztes Jahr
            if (today.getMonth() < 7) { 
                schoolStartYear--; 
            }
            
            // Berechnung des Alters am Stichtag 01.08. des Schuljahres
            // Monat 7 = August (JS zählt ab 0)
            let ageAtSchoolStart = schoolStartYear - dob.getFullYear();
            
            // Hatte der Schüler am 01.08. schon Geburtstag?
            // dob.getMonth() > 7 heißt: Geburtstag ist nach August (Sept-Dez) -> war jünger
            // dob.getMonth() == 7 && dob.getDate() > 1 heißt: Geburtstag im August, aber nach dem 1. -> war jünger
            if (dob.getMonth() > 7 || (dob.getMonth() === 7 && dob.getDate() > 1)) {
                ageAtSchoolStart--;
            }

            outputHtml = '<span style="color:#46b450; font-weight:bold;">Volljährig</span> (' + age + ')';
            
            // Detail-Ausgabe
            if (ageAtSchoolStart >= 18) {
                // War am 01.08. schon 18
                outputHtml += '<br><small style="color:#46b450;">(Zu Schuljahresbeginn 01.08.' + schoolStartYear + ' bereits volljährig)</small>';
            } else {
                // War am 01.08. noch 17 (weil Geburtstag z.B. am 14.08.)
                outputHtml += '<br><small style="color:#d63638;">(Zu Schuljahresbeginn 01.08.' + schoolStartYear + ' noch <u>nicht</u> volljährig)</small>';
            }
            
            isMinor = 0;
        }
        
        statusDisplay.innerHTML = outputHtml;
        statusInput.value = isMinor;
    }
    if(dobInput) { dobInput.addEventListener('change', calcAge); if(dobInput.value) calcAge(); }

    // SYNC DATES (VORSICHTIG!)
    const dateOffInput = document.getElementById('field_date_off'); 
    const protDateInput = document.getElementById('field_prot_date'); 
    const protIssueInput = document.getElementById('field_prot_issue_date'); 
    if(dateOffInput && protDateInput) {
        // Init sync nur wenn Ziel leer (First load)
        if(dateOffInput.value && !protDateInput.value) {
            protDateInput.value = dateOffInput.value;
            protIssueInput.value = dateOffInput.value;
        }
        // Nur syncen bei User Interaktion
        dateOffInput.addEventListener('change', function() {
            protDateInput.value = this.value;
            protIssueInput.value = this.value;
        });
    }

    // TOGGLE LOGIC
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
                if(t.classList.contains('mh-collapsible-section')) t.classList.add('mh-hidden');
                else { t.style.opacity = '0.5'; t.style.pointerEvents = 'none'; }
                t.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = true; i.required = false; });
            } else {
                t.classList.remove('mh-hidden'); t.style.opacity = '1'; t.style.pointerEvents = 'auto';
                t.querySelectorAll('input, select, textarea').forEach(i => {
                    if(i.closest('.toggle-target').id === t.id) {
                        i.disabled = false;
                        if(i.type !== 'hidden' && i.type !== 'checkbox' && i.tagName !== 'TEXTAREA') i.required = true;
                    }
                });
            }
        });
    }
    triggers.forEach(r => r.addEventListener('change', updateToggles));
    // Timeout damit Browser-Autofill nicht dazwischen funkt
    setTimeout(() => { updateToggles(); }, 100);
});
</script>