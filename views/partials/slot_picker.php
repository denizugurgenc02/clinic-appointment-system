<?php
/**
 * Poliklinik -> doktor seçimi + AJAX haftalık slot ızgarası (public/assets/js/slots.js).
 * Seçilen slot gizli "starts_at" alanına yazılır. Bir <form> içine yerleştirilir.
 *
 * @var list<\App\Model\Department> $departments
 * @var list<\App\Model\Doctor>     $doctors
 * @var int|null                    $selectedDoctorId
 * @var int|null                    $fixedDoctorId Tarih değiştirmede doktor sabittir
 * @var array<string, mixed>        $old
 */
$fixedDoctorId ??= null;
$selectedDoctorId ??= null;
$old ??= [];
$selectedDoctorId = $fixedDoctorId ?? (isset($old['doctor_id']) ? (int) $old['doctor_id'] : $selectedDoctorId);
$selectedDepartmentId = null;
foreach ($doctors ?? [] as $d) {
    if ($d->id === $selectedDoctorId) {
        $selectedDepartmentId = $d->departmentId;
    }
}
?>
<div data-slot-picker>
    <?php if ($fixedDoctorId !== null): ?>
        <input type="hidden" name="doctor_id" value="<?= e($fixedDoctorId) ?>" data-doctor>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <label for="department_id" class="form-label">Poliklinik</label>
                <select id="department_id" class="form-select" data-department>
                    <option value="">Tüm poliklinikler</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department->id) ?>"<?= $department->id === $selectedDepartmentId ? ' selected' : '' ?>>
                            <?= e($department->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="doctor_id" class="form-label">Doktor</label>
                <select id="doctor_id" name="doctor_id" class="form-select" data-doctor required>
                    <option value="">Doktor seçin</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?= e($doctor->id) ?>"<?= $doctor->id === $selectedDoctorId ? ' selected' : '' ?>>
                            <?= e($doctor->displayName()) ?> (<?= e($doctor->departmentName) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <input type="hidden" name="starts_at" value="" data-start-input>

    <div class="slot-grid border rounded bg-white p-2 p-md-3" data-grid aria-live="polite">
        <p class="text-muted mb-0">Boş saatleri görmek için bir doktor seçin.</p>
    </div>

    <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
        <span><span class="legend legend-available"></span> Boş</span>
        <span><span class="legend legend-booked"></span> Dolu</span>
        <span><span class="legend legend-selected"></span> Seçili</span>
    </div>

    <div class="alert alert-info mt-3 mb-0 d-none" data-selection></div>
</div>
