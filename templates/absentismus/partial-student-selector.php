<?php
/**
 * Partial: Klassen- & Schülerwahl (Klasse/Schüler-Dropdown per AJAX,
 * Klassenleitung, minderjährig/schulpflichtig). Wiederverwendet von
 * fall-open.php und standalone-step-form.php, damit dieser Block nur an
 * einer Stelle gepflegt werden muss.
 *
 * Erwartet aus dem Elternscope: $val, $checked (Helfer-Closures), $classes_list.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user     = wp_get_current_user();
$teacher_default   = trim( $current_user->first_name . ' ' . $current_user->last_name ) ?: $current_user->display_name;
?>
<div class="mh-form-section">
	<h4>Klassen- &amp; Schülerwahl</h4>
	<div class="mh-grid-row mh-grid-2">
		<div class="mh-input-group">
			<label>Klasse <span class="req">*</span></label>
			<select name="class_wu_id" id="mh_class_select" required>
				<option value="">-- Bitte wählen --</option>
				<?php if ( ! empty( $classes_list ) ) : foreach ( $classes_list as $c ) : ?>
					<option value="<?= (int) $c['wu_id'] ?>" data-name="<?= esc_attr( $c['name'] ) ?>" <?= selected( $val( 'class_wu_id' ), $c['wu_id'] ) ?>>
						<?= esc_html( $c['name'] ) ?>
					</option>
				<?php endforeach; endif; ?>
			</select>
			<input type="hidden" name="class_name" id="class_name_hidden" value="<?= $val( 'class_name' ) ?>">
		</div>
		<div class="mh-input-group">
			<label>Schüler*in <span class="req">*</span></label>
			<select name="student_wu_id" id="mh_student_select" required <?= empty( $val( 'class_wu_id' ) ) ? 'disabled' : '' ?>>
				<option value="">-- Erst Klasse wählen --</option>
			</select>
			<input type="hidden" name="lastname" id="student_lastname" value="<?= $val( 'lastname' ) ?>">
			<input type="hidden" name="firstname" id="student_firstname" value="<?= $val( 'firstname' ) ?>">
			<input type="hidden" name="dob" id="student_dob" value="<?= $val( 'dob' ) ?>">
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
		studentSelect.disabled = false;
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

	const initialClassId = classSelect.value;
	if (initialClassId) {
		fetchStudents(initialClassId, '<?= $val( 'student_wu_id' ) ?>');
	}
});
</script>
