<?php
/**
 * Partial: Klassen- & Schülerwahl (Klasse/Schüler-Dropdown per AJAX,
 * Klassenleitung, minderjährig/schulpflichtig). Wiederverwendet von
 * fall-open.php und standalone-step-form.php, damit dieser Block nur an
 * einer Stelle gepflegt werden muss.
 *
 * Erwartet aus dem Elternscope: $val, $checked, $err_cls (Helfer-Closures),
 * $form_data, $classes_list.
 *
 * Steht ein Schüler nicht in der Klassenliste, kann er per Checkbox
 * "student_manual" mit Name/Vorname/Geburtsdatum manuell erfasst werden
 * (student_wu_id bleibt dann leer).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user     = wp_get_current_user();
$teacher_default   = trim( $current_user->first_name . ' ' . $current_user->last_name ) ?: $current_user->display_name;

// Manuelle Erfassung, falls der Schüler (noch) nicht in der WebUntis-Klassenliste
// steht. Beide Feldsätze (versteckt aus der Liste / sichtbar manuell) heißen
// gleich (lastname/firstname/dob) — der jeweils inaktive wird disabled und
// damit nicht mitgesendet, sodass die Controller unverändert $_POST['lastname']
// usw. lesen können.
$is_manual = '1' === ( $form_data['student_manual'] ?? '' );
?>
<div class="mh-form-section">
	<h4>Klassen- &amp; Schülerwahl</h4>
	<div class="mh-grid-row mh-grid-2">
		<div class="mh-input-group">
			<label>Klasse <span class="req">*</span></label>
			<select name="class_wu_id" id="mh_class_select" class="<?= $err_cls( 'class_wu_id' ) ?>" required>
				<option value="">-- Bitte wählen --</option>
				<?php if ( ! empty( $classes_list ) ) : foreach ( $classes_list as $c ) : ?>
					<option value="<?= (int) $c['wu_id'] ?>" data-name="<?= esc_attr( $c['name'] ) ?>" <?= selected( $val( 'class_wu_id' ), $c['wu_id'] ) ?>>
						<?= esc_html( $c['name'] ) ?>
					</option>
				<?php endforeach; endif; ?>
			</select>
			<input type="hidden" name="class_name" id="class_name_hidden" value="<?= $val( 'class_name' ) ?>">
		</div>
		<div class="mh-input-group" id="mh_student_list_group" style="<?= $is_manual ? 'display:none;' : '' ?>">
			<label>Schüler*in <span class="req">*</span></label>
			<select name="student_wu_id" id="mh_student_select" class="<?= $err_cls( 'student_wu_id' ) ?>" required <?= ( $is_manual || empty( $val( 'class_wu_id' ) ) ) ? 'disabled' : '' ?>>
				<option value="">-- Erst Klasse wählen --</option>
			</select>
			<input type="hidden" name="lastname" id="student_lastname" class="mh-student-list-field" value="<?= $is_manual ? '' : $val( 'lastname' ) ?>" <?= $is_manual ? 'disabled' : '' ?>>
			<input type="hidden" name="firstname" id="student_firstname" class="mh-student-list-field" value="<?= $is_manual ? '' : $val( 'firstname' ) ?>" <?= $is_manual ? 'disabled' : '' ?>>
			<input type="hidden" name="dob" id="student_dob" class="mh-student-list-field" value="<?= $is_manual ? '' : $val( 'dob' ) ?>" <?= $is_manual ? 'disabled' : '' ?>>
		</div>
	</div>

	<div class="checkbox-group" style="margin-bottom:15px;">
		<input type="checkbox" name="student_manual" value="1" id="chk_student_manual" <?= $is_manual ? 'checked' : '' ?>>
		<label for="chk_student_manual">Schüler*in ist <strong>nicht in der Klassenliste</strong> – Daten manuell eingeben</label>
	</div>

	<div class="mh-grid-row mh-grid-3" id="mh_student_manual_group" style="<?= $is_manual ? '' : 'display:none;' ?>">
		<div class="mh-input-group">
			<label>Nachname <span class="req">*</span></label>
			<input type="text" name="lastname" class="mh-student-manual-field <?= $err_cls( 'lastname' ) ?>" value="<?= $is_manual ? $val( 'lastname' ) : '' ?>" <?= $is_manual ? 'required' : 'disabled' ?>>
		</div>
		<div class="mh-input-group">
			<label>Vorname <span class="req">*</span></label>
			<input type="text" name="firstname" class="mh-student-manual-field <?= $err_cls( 'firstname' ) ?>" value="<?= $is_manual ? $val( 'firstname' ) : '' ?>" <?= $is_manual ? 'required' : 'disabled' ?>>
		</div>
		<div class="mh-input-group">
			<label>Geburtsdatum</label>
			<input type="date" name="dob" class="mh-student-manual-field" value="<?= $is_manual ? $val( 'dob' ) : '' ?>" <?= $is_manual ? '' : 'disabled' ?>>
		</div>
	</div>
	<div class="mh-grid-row mh-grid-3">
		<div class="mh-input-group">
			<label>Klassenleitung <span class="req">*</span></label>
			<input type="text" name="teacher" required readonly value="<?= $val( 'teacher' ) ?: esc_attr( $teacher_default ) ?>">
		</div>
		<div class="mh-input-group">
			<label>&nbsp;</label>
			<div class="checkbox-group"><input type="checkbox" name="is_minor" value="1" id="chk_minor" <?= $checked( 'is_minor' ) ?>> <label for="chk_minor">minderjährig</label></div>
		</div>
		<div class="mh-input-group">
			<label>&nbsp;</label>
			<div class="checkbox-group"><input type="checkbox" name="is_schulpflichtig" value="1" id="chk_schulpflichtig" <?= $checked( 'is_schulpflichtig' ) ?>> <label for="chk_schulpflichtig">schulpflichtig</label></div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const classSelect   = document.getElementById('mh_class_select');
	const studentSelect = document.getElementById('mh_student_select');
	const classHidden   = document.getElementById('class_name_hidden');
	const fLast  = document.getElementById('student_lastname');
	const fFirst = document.getElementById('student_firstname');
	const fDob   = document.getElementById('student_dob');

	function fetchStudents(classId, selectedStudentId) {
		if (!classId) {
			studentSelect.disabled = true;
			studentSelect.innerHTML = '<option value="">-- Erst Klasse wählen --</option>';
			return;
		}
		studentSelect.disabled = document.getElementById('chk_student_manual').checked;
		studentSelect.innerHTML = '<option value="">Lade Klassenliste...</option>';

		const formData = new FormData();
		formData.append('action', 'mh_get_students');
		formData.append('class_id', classId);
		formData.append('nonce', '<?php echo wp_create_nonce( 'mh_form_nonce' ); ?>');

		fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', { method: 'POST', body: formData })
			.then(r => r.json())
			.then(data => {
				studentSelect.innerHTML = '<option value="">-- Schüler wählen --</option>';
				if (data.success && data.data) {
					data.data.forEach(s => {
						const isSelected = (selectedStudentId && s.wu_id == selectedStudentId) ? 'selected' : '';
						studentSelect.innerHTML += `<option value="${s.wu_id}" data-last="${s.name}" data-first="${s.fore_name}" data-dob="${s.dob || ''}" ${isSelected}>${s.name}, ${s.fore_name}</option>`;
					});
				}
			}).catch(err => console.error('Fehler:', err));
	}

	classSelect.addEventListener('change', function () {
		const opt = this.options[this.selectedIndex];
		classHidden.value = opt ? (opt.dataset.name || '') : '';
		fetchStudents(this.value);
	});

	studentSelect.addEventListener('change', function () {
		const opt = this.options[this.selectedIndex];
		if (this.value) {
			fLast.value = opt.dataset.last || '';
			fFirst.value = opt.dataset.first || '';
			fDob.value = opt.dataset.dob || '';
		}
	});

	// Umschalten Liste <-> manuelle Eingabe: der jeweils inaktive Feldsatz wird
	// disabled (wird dann nicht mitgesendet und blockiert kein "required").
	const chkManual   = document.getElementById('chk_student_manual');
	const listGroup   = document.getElementById('mh_student_list_group');
	const manualGroup = document.getElementById('mh_student_manual_group');
	chkManual.addEventListener('change', function () {
		const manual = this.checked;
		listGroup.style.display   = manual ? 'none' : '';
		manualGroup.style.display = manual ? '' : 'none';
		studentSelect.disabled = manual || !classSelect.value;
		document.querySelectorAll('.mh-student-list-field').forEach(function (el) { el.disabled = manual; });
		document.querySelectorAll('.mh-student-manual-field').forEach(function (el) {
			el.disabled = !manual;
			if ('text' === el.type) el.required = manual;
		});
		// Zurück zur Liste, aber Klassenliste wurde noch nie geladen (Seite im
		// manuellen Modus geöffnet) -> jetzt nachladen.
		if (!manual && classSelect.value && studentSelect.options.length <= 1) {
			fetchStudents(classSelect.value);
		}
	});

	const initialClassId = classSelect.value;
	if (initialClassId && !chkManual.checked) {
		fetchStudents(initialClassId, '<?= $val( 'student_wu_id' ) ?>');
	}
});
</script>
