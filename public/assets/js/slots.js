/**
 * Haftalık slot ızgarası (AJAX).
 *
 * [data-slot-picker] kapsayıcısı içinde:
 *  - [data-department] seçilince  GET /api/doctors?department_id=..  ile doktor listesi yenilenir
 *  - [data-doctor] seçilince       GET /api/slots?doctor_id=..&week=..  ile ızgara JSON olarak yüklenir
 *  - Boş slota tıklanınca başlangıç saati [data-start-input] gizli alanına yazılır, gönder düğmesi açılır
 *
 * Sunucudan gelen tüm metinler textContent ile basılır (innerHTML yok) -> XSS'e kapalı.
 */
'use strict';

(function () {
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function initPicker(root) {
        const departmentSelect = root.querySelector('[data-department]');
        const doctorInput = root.querySelector('[data-doctor]');
        const grid = root.querySelector('[data-grid]');
        const startInput = root.querySelector('[data-start-input]');
        const selection = root.querySelector('[data-selection]');
        const form = root.closest('form');
        const submit = form ? form.querySelector('[data-submit]') : null;
        const waitlistDoctor = document.querySelector('[data-waitlist-doctor]');
        const waitlistSubmit = document.querySelector('[data-waitlist-submit]');
        let week = '';

        function message(text, className) {
            grid.replaceChildren(el('p', className || 'text-muted mb-0', text));
        }

        function resetSelection() {
            startInput.value = '';
            if (submit) {
                submit.disabled = true;
            }
            if (selection) {
                selection.classList.add('d-none');
            }
        }

        function syncWaitlist() {
            if (waitlistDoctor) {
                waitlistDoctor.value = doctorInput.value;
            }
            if (waitlistSubmit) {
                waitlistSubmit.disabled = !doctorInput.value;
            }
        }

        async function loadDoctors() {
            const params = new URLSearchParams();
            if (departmentSelect.value) {
                params.set('department_id', departmentSelect.value);
            }
            try {
                const data = await App.fetchJson('/api/doctors?' + params.toString());
                const placeholder = el('option', '', data.doctors.length ? 'Doktor seçin' : 'Bu poliklinikte doktor yok');
                placeholder.value = '';
                doctorInput.replaceChildren(placeholder);
                data.doctors.forEach(function (doctor) {
                    const option = el('option', '', doctor.name + ' (' + doctor.department + ')');
                    option.value = String(doctor.id);
                    doctorInput.appendChild(option);
                });
            } catch (error) {
                message(error.message, 'text-danger mb-0');
            }
            week = '';
            resetSelection();
            syncWaitlist();
            message('Boş saatleri görmek için bir doktor seçin.');
        }

        async function loadSlots() {
            resetSelection();
            syncWaitlist();
            if (!doctorInput.value) {
                message('Boş saatleri görmek için bir doktor seçin.');
                return;
            }

            message('Yükleniyor…');
            const params = new URLSearchParams({ doctor_id: doctorInput.value });
            if (week) {
                params.set('week', week);
            }

            try {
                render(await App.fetchJson('/api/slots?' + params.toString()));
            } catch (error) {
                message(error.message, 'text-danger mb-0');
            }
        }

        function navButton(label, targetWeek) {
            const button = el('button', 'btn btn-sm btn-outline-secondary', label);
            button.type = 'button';
            button.disabled = !targetWeek;
            button.addEventListener('click', function () {
                week = targetWeek;
                loadSlots();
            });
            return button;
        }

        function render(data) {
            const header = el('div', 'd-flex justify-content-between align-items-center gap-2 mb-3');
            const title = el('div');
            title.appendChild(el('strong', '', data.doctor.name));
            title.appendChild(el('div', 'small text-muted', data.week_label + ' · ' + data.doctor.slot_minutes + ' dk'));
            header.append(navButton('‹ Önceki', data.prev_week), title, navButton('Sonraki ›', data.next_week));

            const row = el('div', 'row g-2');
            data.days.forEach(function (day) {
                const col = el('div', 'col-12 col-md slot-day');
                col.appendChild(el('div', 'slot-day-title', day.label));
                const list = el('div', 'd-flex flex-wrap flex-md-column gap-1');

                const visible = day.slots.filter(function (slot) { return slot.status !== 'past'; });
                if (visible.length === 0) {
                    list.appendChild(el('span', 'small text-muted', day.slots.length ? 'Geçti' : 'Çalışma yok'));
                }

                visible.forEach(function (slot) {
                    const available = slot.status === 'available';
                    const button = el('button', 'btn btn-sm slot ' + (available ? 'slot-available' : 'slot-booked'), slot.time);
                    button.type = 'button';
                    button.disabled = !available;
                    button.title = available ? 'Boş' : 'Dolu';
                    if (available) {
                        button.addEventListener('click', function () {
                            grid.querySelectorAll('.slot-selected').forEach(function (b) { b.classList.remove('slot-selected'); });
                            button.classList.add('slot-selected');
                            startInput.value = slot.start;
                            if (submit) {
                                submit.disabled = false;
                            }
                            if (selection) {
                                selection.textContent = 'Seçilen: ' + day.day_name + ' ' + day.label.split(' ')[1] + ' saat ' + slot.time;
                                selection.classList.remove('d-none');
                            }
                        });
                    }
                    list.appendChild(button);
                });

                col.appendChild(list);
                row.appendChild(col);
            });

            grid.replaceChildren(header, row);

            if (data.available_count === 0) {
                grid.appendChild(el('p', 'alert alert-warning mt-3 mb-0', 'Bu hafta boş saat yok. Sonraki haftaya bakabilir veya bekleme listesine girebilirsiniz.'));
            }
        }

        if (departmentSelect) {
            departmentSelect.addEventListener('change', loadDoctors);
        }
        doctorInput.addEventListener('change', function () {
            week = '';
            loadSlots();
        });

        if (doctorInput.value) {
            loadSlots();
        } else {
            syncWaitlist();
        }
    }

    document.querySelectorAll('[data-slot-picker]').forEach(initPicker);
})();
