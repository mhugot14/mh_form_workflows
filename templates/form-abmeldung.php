<?php
// Helper Variablen
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';
?>

<style>
    /* --- RESET & LAYOUT SICHERHEIT --- */
    /* Wir isolieren unser Formular, damit Theme-Styles weniger Schaden anrichten */
    .mh-form-wrapper {
        max-width: 900px;
        margin: 0 auto;
        font-family: inherit; /* Nimm Schriftart vom Theme */
    }

    /* WICHTIG: Damit Padding die Breite nicht sprengt */
    .mh-form-wrapper *, 
    .mh-form-wrapper *::before, 
    .mh-form-wrapper *::after {
        box-sizing: border-box; 
    }

    .mh-form-section {
        background: #f9f9f9;
        border: 1px solid #ccc;
        padding: 25px;
        margin-bottom: 25px;
        border-radius: 4px;
    }

    .mh-form-section h4 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
        font-size: 1.2em;
        color: #333;
    }

    /* --- GRID SYSTEM FÜR ZEILEN --- */
    /* Ersetzt das anfällige Flexbox */
    .mh-grid-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Automatische Spalten, mind. 220px breit */
        gap: 20px;
        margin-bottom: 15px;
        align-items: end; /* Labels und Felder unten bündig, falls Labels umbrechen */
    }
    
    .mh-grid-2 { grid-template-columns: 1fr 1fr; } /* Erzwinge 2 Spalten wenn Platz da ist */
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr; } /* Erzwinge 3 Spalten */

    /* --- INPUTS & LABELS HÄRTEN --- */
    .mh-form-wrapper label {
        display: block !important; /* Theme überschreiben */
        font-weight: bold;
        margin-bottom: 6px;
        color: #333;
        line-height: 1.3;
        width: 100%;
    }

    .mh-form-wrapper input[type="text"],
    .mh-form-wrapper input[type="date"],
    .mh-form-wrapper input[type="number"],
    .mh-form-wrapper select {
        width: 100% !important; /* Theme Zwang */
        max-width: 100%;
        height: 40px !important; /* Einheitliche Höhe */
        padding: 6px 12px !important;
        border: 1px solid #ccc;
        border-radius: 4px;
        background-color: #fff;
        display: block;
        margin: 0;
        font-size: 15px; /* Lesbare Schriftgröße */
    }

    /* --- RADIO BUTTONS & CHECKBOXES --- */
    .radio-group {
        display: flex !important;
        flex-direction: row;
        align-items: flex-start;
        margin-bottom: 8px;
        gap: 10px;
    }

    /* Input Reset für Radios damit sie nicht riesig werden */
    .mh-form-wrapper input[type="radio"],
    .mh-form-wrapper input[type="checkbox"] {
        width: 18px !important;
        height: 18px !important;
        margin-top: 3px !important;
        flex-shrink: 0;
        cursor: pointer;
    }
    
    .radio-group label {
        font-weight: normal !important;
        margin-bottom: 0 !important;
        cursor: pointer;
        display: inline-block !important;
    }

    /* --- SONSTIGES --- */
    .mh-sub-group {
        margin-left: 28px;
        padding: 15px;
        border-left: 3px solid #ddd;
        background: #fff;
        margin-bottom: 15px;
        margin-top: 5px;
    }
    
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    
    /* Buttons */
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 10px 20px !important; }

    /* Validierung Styles */
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
    .mh-valid-field { border-color: #46b450 !important; background-color: #f6fff7 !important; }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box">
            <h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3>
            <p style="margin:0;">Alle Daten sind plausibel. Das PDF wird erstellt.</p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">⚠️ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;">
                 <?php foreach($form_errors as $e) echo "<li>$e</li>"; ?>
             </ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- SEKTION 1: Stammdaten -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            
            <!-- Grid Zeile 1: Name, Vorname, Geburtsdatum -->
            <div class="mh-grid-row mh-grid-3">
                <div>
                    <label>Nachname <span class="req">*</span></label>
                    <input type="text" name="lastname" required class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>">
                </div>
                <div>
                    <label>Vorname <span class="req">*</span></label>
                    <input type="text" name="firstname" required class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>">
                </div>
                <div>
                    <label>Geburtsdatum <span class="req">*</span></label>
                    <input type="date" name="dob" id="field_dob" required class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>">
                </div>
            </div>

            <!-- Grid Zeile 2: Klasse, Lehrer -->
            <div class="mh-grid-row mh-grid-2">
                <div>
                    <label>Klasse <span class="req">*</span></label>
                    <input type="text" name="class_name" required class="<?= $err_cls('class_name') ?>" value="<?= $val('class_name') ?>">
                </div>
                <div>
                    <label>Klassenlehrer/in (Kürzel)</label>
                    <input type="text" name="teacher" class="<?= $err_cls('teacher') ?>" value="<?= $val('teacher') ?>">
                </div>
            </div>

            <!-- Grid Zeile 3: Status & Abmeldedatum -->
            <div class="mh-grid-row mh-grid-2">
                <div>
                    <label>Status (berechnet)</label>
                    <!-- Ein fake input für das Design, readonly -->
                    <div style="height: 40px; padding: 8px 12px; background: #e9e9e9; border: 1px solid #ccc; border-radius: 4px; display:flex; align-items:center;">
                        <span id="status_display" style="font-weight:500;">...</span>
                        <input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>">
                    </div>
                </div>
                <div>
                    <label>Datum der Abmeldung <span class="req">*</span></label>
                    <input type="date" name="date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 2: Grund -->
        <div class="mh-form-section">
            <h4>1. Grund der Abmeldung <span class="req">*</span></h4>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="schulwechsel" id="r_wechsel" class="toggle-trigger" data-target="new_school_wrap" required <?= $chk('reason', 'schulwechsel') ?>> 
                <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule)</label>
            </div>
            
            <div id="new_school_wrap" class="mh-sub-group toggle-target">
                <label>Name und Ort der Schule:</label>
                <input type="text" name="new_school" placeholder="z.B. Muster-Berufskolleg, Musterstadt" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>">
            </div>

            <div class="radio-group">
                <input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> 
                <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> 
                <label for="r_beschl">Ausschulung Beschluss Teilkonferenz</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> 
                <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> 
                <label for="r_abm">Abmeldung</label>
            </div>
        </div>

        <!-- SEKTION 3: Schulpflicht -->
        <div class="mh-form-section">
            <h4>2. Schulpflicht <span class="req">*</span></h4>
            
            <div class="radio-group">
                <input type="radio" name="compulsory" value="fulfilled" id="c_full" class="toggle-trigger" required <?= $chk('compulsory', 'fulfilled') ?>> 
                <label for="c_full">Die Schulpflicht ist erfüllt.</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="compulsory" value="not_fulfilled" id="c_not" class="toggle-trigger" <?= $chk('compulsory', 'not_fulfilled') ?>> 
                <label for="c_not">Die Schulpflicht ist NICHT erfüllt (Schulpflichtverfolgung...).</label>
            </div>

            <!-- AV Klasse -->
            <div class="radio-group">
                <input type="radio" name="compulsory" value="av_klasse" id="c_av" class="toggle-trigger" data-target="av_details" <?= $chk('compulsory', 'av_klasse') ?>> 
                <label for="c_av">Wechsel in AV-Klasse</label>
            </div>
            
            <div id="av_details" class="mh-sub-group toggle-target">
                <!-- Grid innerhalb der Sub-Gruppe -->
                <div class="mh-grid-row mh-grid-3">
                    <div>
                        <label>Zum Datum:</label>
                        <input type="date" name="av_date_start" value="<?= $val('av_date_start') ?>">
                    </div>
                    <div>
                        <label>Gespräch mit:</label>
                        <input type="text" name="av_talk_with" placeholder="Lehrername" value="<?= $val('av_talk_with') ?>">
                    </div>
                    <div>
                        <label>am:</label>
                        <input type="date" name="av_talk_date" value="<?= $val('av_talk_date') ?>">
                    </div>
                </div>
            </div>

            <!-- Bildungsgang -->
            <div class="radio-group">
                <input type="radio" name="compulsory" value="bildungsgang" id="c_bg" class="toggle-trigger" data-target="bg_details" <?= $chk('compulsory', 'bildungsgang') ?>> 
                <label for="c_bg">Wechsel in den Bildungsgang...</label>
            </div>
            <div id="bg_details" class="mh-sub-group toggle-target">
                <label>Name des Bildungsgangs:</label>
                <input type="text" name="new_education_track" value="<?= $val('new_education_track') ?>">
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis -->
        <div class="mh-form-section">
            <h4>3. Zeugnis</h4>
             
             <div class="radio-group" style="padding-bottom:15px; margin-bottom:15px; border-bottom:1px dashed #ccc;">
                <input type="checkbox" name="protocol_attached" value="1" id="chk_protocol" <?= $chk('protocol_attached', '1') ?>>
                <label for="chk_protocol" style="font-weight:bold !important;">Zeugniskonferenzprotokoll beifügen</label>
             </div>

             <div class="radio-group">
                <input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="cert-trigger toggle-trigger" <?= $chk('certificate', 'ueberweisung') ?>>
                <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <br><small style="color:#666;">(Wechsel innerhalb Schulstufe)</small></label>
            </div>
             
             <div class="radio-group">
                <input type="radio" name="certificate" value="abgang" id="z_ab" class="cert-trigger toggle-trigger" <?= $chk('certificate', 'abgang') ?>>
                <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <br><small style="color:#666;">(Ohne Abschluss)</small></label>
            </div>
            
            <!-- Fehlstunden in Grid -->
            <div class="mh-grid-row mh-grid-2" style="margin-top:15px;">
                <div>
                    <label>Fehlstunden Gesamt:</label>
                    <input type="number" name="missed_hours" class="hours-input" disabled value="<?= $val('missed_hours') ?>">
                </div>
                <div>
                    <label>davon unentschuldigt:</label>
                    <input type="number" name="missed_ue" class="hours-input" disabled value="<?= $val('missed_ue') ?>">
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular prüfen</button>
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
        </div>
    </form>
</div>

<!-- JAVASCRIPT LOGIK BLEIBT GLEICH -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Generic Toggle Logic
    const triggers = document.querySelectorAll('.toggle-trigger');
    const targets  = document.querySelectorAll('.toggle-target');

    function updateToggles() {
        targets.forEach(el => {
            const inputs = el.querySelectorAll('input, select');
            // Check if input is ALREADY disabled (from previous logic), don't overwrite blindly
            inputs.forEach(i => i.disabled = true);
            el.style.opacity = '0.5';
            el.style.pointerEvents = 'none'; // Verhindert Klicks im deaktivierten Bereich
        });

        triggers.forEach(radio => {
            if(radio.checked && radio.dataset.target) {
                const targetId = radio.dataset.target;
                const targetEl = document.getElementById(targetId);
                if(targetEl) {
                    targetEl.style.opacity = '1';
                    targetEl.style.pointerEvents = 'auto';
                    const inputs = targetEl.querySelectorAll('input, select');
                    inputs.forEach(i => i.disabled = false);
                }
            }
        });
    }
    triggers.forEach(radio => radio.addEventListener('change', updateToggles));
    
    // 2. Fehlstunden Logic
    const certRadios = document.querySelectorAll('.cert-trigger');
    const hourInputs = document.querySelectorAll('.hours-input');
    function updateHours() {
        let isUe = false;
        certRadios.forEach(r => { if(r.checked && r.value === 'ueberweisung') isUe = true; });
        hourInputs.forEach(i => {
            i.disabled = !isUe;
            if(!isUe) {
                 i.style.backgroundColor = '#eee';
            } else {
                 i.style.backgroundColor = '#fff';
            }
        });
    }
    certRadios.forEach(r => r.addEventListener('change', updateHours));

    // 3. Alter Logic
    const dobInput = document.getElementById('field_dob');
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');

    function calcAge() {
        if(!dobInput.value) return;
        const dob = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }

        if (age < 18) {
            statusDisplay.innerHTML = '<span style="color:#d63638">Minderjährig</span> (' + age + ')';
            statusInput.value = '1';
        } else {
            statusDisplay.innerHTML = '<span style="color:#46b450">Volljährig</span> (' + age + ')';
            statusInput.value = '0';
        }
    }
    if(dobInput) {
        dobInput.addEventListener('change', calcAge);
        if(dobInput.value) calcAge();
    }

    // Init
    updateToggles();
    // Timeout, damit Browser Autofill abgewartet wird
    setTimeout(() => { updateHours(); }, 100);
});
</script>