<?php
/**
 * View Variablen:
 * $form_data (array) - Vorbefüllte Daten
 * $form_errors (array) - Felder mit Fehlern
 * $is_success (bool) - Ob der Check erfolgreich war
 */

// Helper Funktionen für sauberen Code im HTML
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';
?>

<style>
    .mh-form-wrapper { max-width: 800px; margin: 0 auto; } /* Optional: Begrenzt die Breite */
    
    .mh-form-section { 
        border: 1px solid #ccc; 
        padding: 20px; 
        margin-bottom: 20px; 
        background: #f9f9f9; 
        border-radius: 4px; /* Sieht moderner aus */
    }
    
    .mh-form-section h4 { 
        margin-top: 0; 
        border-bottom: 1px solid #ddd; 
        padding-bottom: 10px; 
        margin-bottom: 15px;
    }
    
    /* Generelles Layout für Inputs */
    .form-row { margin-bottom: 15px; }
    
    /* Standard Labels (über dem Feld) */
    label { 
        font-weight: bold; 
        display: block; 
        margin-bottom: 5px; 
        color: #333;
    }
    
    /* --- RADIO BUTTON FIX --- */
    /* Wir machen den Container zum Flex-Container -> Kinder liegen nebeneinander */
    .radio-group {
        display: flex !important;
        align-items: flex-start; /* Wichtig: Oben bündig, falls Text zweizeilig ist */
        gap: 10px;               /* Abstand zwischen Kreis und Text */
        margin-bottom: 8px;
    }
    
    /* Der Radio Button selbst */
    .radio-group input[type="radio"],
    .radio-group input[type="checkbox"] {
        margin-top: 3px !important; /* Optische Korrektur, damit er mittig zur ersten Textzeile sitzt */
        margin-bottom: 0 !important;
        width: auto !important;     /* Verhindert, dass Themes den Button 100% breit machen */
        flex-shrink: 0;             /* Verhindert, dass der Button eingedrückt wird */
    }
    
    /* Das Label daneben */
    .radio-group label {
        display: inline-block !important;
        font-weight: normal !important;
        margin: 0 !important;
        line-height: 1.4; /* Bessere Lesbarkeit */
    }
    
    /* Einrückungen für Zusatzfelder */
    .indent { 
        margin-left: 28px; /* Bündig zum Text darüber */
        margin-top: 5px; 
        padding-left: 10px; 
        border-left: 2px solid #ddd; 
    }
    
    /* Validierung & Farben */
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    
    input:disabled { background-color: #eee; cursor: not-allowed; opacity: 0.6; }
    
    /* Error / Success Felder */
    .mh-error-field { border: 1px solid #d63638 !important; background-color: #fff5f5 !important; }
    .mh-valid-field { border: 1px solid #46b450 !important; background-color: #f6fff7 !important; }
    
    /* Boxen */
    .mh-success-box { background: #fff; border-left: 4px solid #46b450; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .mh-error-box { background: #fff; border-left: 4px solid #d63638; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    
    .mh-valid-field + label::after { content: ' ✓'; color: #46b450; font-weight: bold; }
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box">
            <h3 style="margin:0; color: #00a32a;">✅ Prüfung erfolgreich!</h3>
            <p>Alle Daten sind plausibel. Sie können jetzt das PDF erstellen.</p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
            <h3 style="margin:0; color: #d63638;">⚠️ Bitte korrigieren:</h3>
            <ul>
                <?php foreach($form_errors as $e_msg): ?>
                    <li><?= esc_html($e_msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- BASIS DATEN -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="form-row" style="flex:1; min-width: 200px;">
                    <label>Nachname <span class="req">*</span>:</label>
                    <input type="text" name="lastname" required class="widefat <?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>">
                </div>
                <div class="form-row" style="flex:1; min-width: 200px;">
                    <label>Vorname <span class="req">*</span>:</label>
                    <input type="text" name="firstname" required class="widefat <?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>">
                </div>
                <div class="form-row" style="flex:1; min-width: 200px;">
                    <label>Geburtsdatum <span class="req">*</span>:</label>
                    <input type="date" name="dob" id="field_dob" required class="widefat <?= $err_cls('dob') ?>" value="<?= $val('dob') ?>">
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="form-row" style="flex:1; min-width: 200px;">
                    <label>Klasse <span class="req">*</span>:</label>
                    <input type="text" name="class_name" required class="widefat <?= $err_cls('class_name') ?>" value="<?= $val('class_name') ?>">
                </div>
                <div class="form-row" style="flex:1; min-width: 200px;">
                    <!-- Angepasstes Label -->
                    <label>Klassenlehrer/in (Kürzel):</label>
                    <input type="text" name="teacher" class="widefat" value="<?= $val('teacher') ?>">
                </div>
            </div>
             <div class="form-row">
                <label>Status (wird berechnet):</label>
                <div style="background: #e5e5e5; padding: 10px; border-radius: 4px;">
                    <span id="status_display">...</span>
                    <!-- PHP füllt dieses Feld auch, damit der Controller es zurückbekommt -->
                    <input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>">
                </div>
            </div>
            <div class="form-row">
                 <label>Datum der Abmeldung <span class="req">*</span>:</label>
                 <!-- Default Wert ist heute, außer Form Data existiert -->
                 <input type="date" name="date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>" class="<?= $err_cls('date_off') ?>">
            </div>
        </div>

        <!-- 1. GRUND DER ABMELDUNG -->
        <div class="mh-form-section">
            <h4>1. Grund der Abmeldung <span class="req">*</span></h4>
            
            <div class="form-row radio-group">
                <input type="radio" name="reason" value="schulwechsel" id="r_wechsel" required <?= $chk('reason', 'schulwechsel') ?>> 
                <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule):</label>
                <div class="indent">
                    <input type="text" name="new_school" id="field_new_school" 
                           placeholder="Name der neuen Schule" 
                           style="width: 100%;"
                           class="<?= $err_cls('new_school') ?>" 
                           value="<?= $val('new_school') ?>">
                </div>
            </div>
            
            <div class="form-row radio-group">
                <input type="radio" name="reason" value="aufloesung" id="r_aufl" <?= $chk('reason', 'aufloesung') ?>>
                <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label>
            </div>

            <div class="form-row radio-group">
                <input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" <?= $chk('reason', 'ausschulung_beschluss') ?>>
                <label for="r_beschl">Ausschulung Beschluss Teilkonferenz</label>
            </div>

             <div class="form-row radio-group">
                <input type="radio" name="reason" value="abmeldung" id="r_abm" <?= $chk('reason', 'abmeldung') ?>>
                <label for="r_abm">Sonstige Abmeldung</label>
            </div>
        </div>

        <!-- 2. SCHULPFLICHT -->
        <div class="mh-form-section">
            <h4>2. Schulpflicht</h4>
             <div class="form-row radio-group">
                <input type="radio" name="compulsory" value="fulfilled" id="c_ok" <?= $chk('compulsory', 'fulfilled') ?>>
                <label for="c_ok">Die Schulpflicht ist erfüllt.</label>
            </div>
             <div class="form-row radio-group">
                <input type="radio" name="compulsory" value="not_fulfilled" id="c_not" <?= $chk('compulsory', 'not_fulfilled') ?>>
                <label for="c_not">Die Schulpflicht ist NICHT erfüllt.</label>
            </div>
        </div>

        <!-- 3. ZEUGNIS -->
        <div class="mh-form-section">
            <h4>3. Zeugnis</h4>
             <div class="form-row radio-group">
                <input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="cert-trigger" <?= $chk('certificate', 'ueberweisung') ?>>
                <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG</label>
            </div>
             <div class="form-row radio-group">
                <input type="radio" name="certificate" value="abgang" id="z_ab" class="cert-trigger" <?= $chk('certificate', 'abgang') ?>>
                <label for="z_ab">Abgangszeugnis gem. § 49 SchulG</label>
            </div>
            
            <div class="form-row" style="margin-top: 10px;">
                <label>Fehlstunden Gesamt: 
                    <input type="number" name="missed_hours" class="hours-input <?= $err_cls('missed_ue') ?>" 
                           style="width: 80px;" disabled value="<?= $val('missed_hours') ?>"> 
                </label>
                <br>
                <label>davon unentschuldigt: 
                    <input type="number" name="missed_ue" class="hours-input <?= $err_cls('missed_ue') ?>" 
                           style="width: 80px;" disabled value="<?= $val('missed_ue') ?>">
                </label>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">
                Formular prüfen
            </button>
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">
                Prüfen & PDF erstellen
            </button>
        </div>
    </form>
</div>

<!-- JS Logik für Status & Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Status (Minderjährig) Calculation
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
            statusDisplay.innerHTML = 'Status: <b>Minderjährig</b> (' + age + ' Jahre)';
            statusDisplay.style.color = 'orange';
            statusInput.value = '1';
        } else {
            statusDisplay.innerHTML = 'Status: <b>Volljährig</b> (' + age + ' Jahre)';
            statusDisplay.style.color = 'green';
            statusInput.value = '0';
        }
    }
    
    dobInput.addEventListener('change', calcAge);
    // Init beim Laden (falls reloaded mit Daten)
    if(dobInput.value) calcAge();


    // Fehlstunden Logic
    const certRadios = document.querySelectorAll('.cert-trigger');
    const hourInputs = document.querySelectorAll('.hours-input');

    function toggleHours() {
        let isUeberweisung = false;
        certRadios.forEach(radio => {
            if(radio.checked && radio.value === 'ueberweisung') isUeberweisung = true;
        });
        hourInputs.forEach(input => {
            input.disabled = !isUeberweisung;
            if(!isUeberweisung) input.value = '';
        });
    }

    certRadios.forEach(radio => radio.addEventListener('change', toggleHours));
    // Init check beim Laden (wichtig für reload)
    toggleHours();
});
</script>