function closeSelect(root) {
    const trigger = root.querySelector('[data-select-trigger]');
    const panel = root.querySelector('[data-select-panel]');
    const chevron = root.querySelector('[data-select-chevron]');
    const chevronBox = root.querySelector('[data-select-chevron-box]');

    if (!panel || panel.classList.contains('hidden')) {
        return;
    }

    panel.classList.add('hidden');
    root.classList.remove('z-40');

    const queueCard = root.closest('[data-queue-card]');
    if (queueCard) {
        queueCard.classList.remove('z-30');
    }

    if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.classList.remove('ring-2', 'ring-amber-500/25', 'border-amber-500');
    }

    if (chevron) {
        chevron.classList.toggle('rotate-180');
    }

    if (chevronBox) {
        chevronBox.classList.remove('bg-amber-500', 'text-zinc-950');
        chevronBox.classList.add('bg-zinc-100', 'text-zinc-500');
    }
}

function closeAllSelects(exceptRoot = null) {
    document.querySelectorAll('[data-ui-select]').forEach((root) => {
        if (root !== exceptRoot) {
            closeSelect(root);
        }
    });

    document.querySelectorAll('[data-queue-card].z-30').forEach((card) => {
        if (!exceptRoot || !card.contains(exceptRoot)) {
            card.classList.remove('z-30');
        }
    });
}

function filterSelectOptions(root, query) {
    const normalizedQuery = query.trim().toLowerCase();
    const options = root.querySelectorAll('[data-select-option]');
    const emptyState = root.querySelector('[data-select-empty]');
    const clearBtn = root.querySelector('[data-select-clear]');
    let visibleCount = 0;

    if (clearBtn) {
        clearBtn.classList.toggle('hidden', normalizedQuery.length === 0);
    }

    options.forEach((opt) => {
        if (opt.getAttribute('data-branch-hidden') === 'true') {
            opt.classList.add('hidden');
            return;
        }
        const searchStr = (opt.getAttribute('data-search') || '').toLowerCase();
        const matches = normalizedQuery === '' || searchStr.includes(normalizedQuery);
        opt.classList.toggle('hidden', !matches);
        if (matches) {
            visibleCount++;
        }
    });

    if (emptyState) {
        emptyState.classList.toggle('hidden', visibleCount > 0);
    }
}

function openSelect(root) {
    closeAllSelects(root);

    const trigger = root.querySelector('[data-select-trigger]');
    const panel = root.querySelector('[data-select-panel]');
    const chevron = root.querySelector('[data-select-chevron]');
    const chevronBox = root.querySelector('[data-select-chevron-box]');
    const searchInput = root.querySelector('[data-select-search]');

    if (!panel) {
        return;
    }

    panel.classList.remove('hidden');
    root.classList.add('z-40');

    const queueCard = root.closest('[data-queue-card]');
    if (queueCard) {
        queueCard.classList.add('z-30');
    }

    if (trigger) {
        trigger.setAttribute('aria-expanded', 'true');
        trigger.classList.add('ring-2', 'ring-amber-500/25', 'border-amber-500');
    }

    if (chevron) {
        chevron.classList.toggle('rotate-180');
    }

    if (chevronBox) {
        chevronBox.classList.remove('bg-zinc-100', 'text-zinc-500');
        chevronBox.classList.add('bg-amber-500', 'text-zinc-950');
    }

    if (searchInput) {
        searchInput.value = '';
        filterSelectOptions(root, '');
        requestAnimationFrame(() => {
            searchInput.focus({ preventScroll: true });
        });
    }
}

function applyOptionButtonStyle(btn, isSelected) {
    btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    btn.classList.toggle('bg-amber-500/15', isSelected);
    btn.classList.toggle('text-zinc-950', isSelected);
    btn.classList.toggle('font-bold', isSelected);
    btn.classList.toggle('text-zinc-700', !isSelected);
    btn.classList.toggle('hover:bg-zinc-100', !isSelected);
    btn.classList.toggle('font-medium', !isSelected);

    const check = btn.querySelector('[data-select-check]');
    if (check) {
        check.classList.toggle('hidden', !isSelected);
    }
}

function selectOption(root, optionBtn) {
    const isMultiple = root.getAttribute('data-multiple') === 'true';
    const inputsContainer = root.querySelector('[data-select-inputs]');
    const inputName = root.getAttribute('data-input-name') || '';
    const labelEl = root.querySelector('[data-select-label]');
    const sublabelEl = root.querySelector('[data-select-sublabel]');
    const countEl = root.querySelector('[data-select-count]');

    if (isMultiple) {
        const currentlySelected = optionBtn.getAttribute('aria-selected') === 'true';
        const allSelectedBtns = Array.from(root.querySelectorAll('[data-select-option][aria-selected="true"]'));

        // Pastikan minimal 1 layanan tetap terpilih
        if (currentlySelected && allSelectedBtns.length <= 1) {
            return;
        }

        applyOptionButtonStyle(optionBtn, !currentlySelected);

        const updatedSelected = Array.from(root.querySelectorAll('[data-select-option][aria-selected="true"]'));

        if (inputsContainer) {
            inputsContainer.innerHTML = '';
            updatedSelected.forEach((btn) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = btn.getAttribute('data-value') ?? '';
                input.setAttribute('data-select-input', '');
                inputsContainer.appendChild(input);
            });
        }

        if (labelEl) {
            labelEl.textContent = updatedSelected.map((b) => b.getAttribute('data-label') ?? '').join(', ');
        }

        if (sublabelEl) {
            if (updatedSelected.length > 1) {
                sublabelEl.textContent = `${updatedSelected.length} dipilih`;
                sublabelEl.classList.remove('hidden');
            } else {
                sublabelEl.textContent = '';
                sublabelEl.classList.add('hidden');
            }
        }

        if (countEl) {
            countEl.textContent = `${updatedSelected.length} dipilih`;
        }

        const form = root.closest('form');
        if (form) {
            syncColoringPickerVisibility(form);
        }

        return;
    }

    const value = optionBtn.getAttribute('data-value') ?? '';
    const label = optionBtn.getAttribute('data-label') ?? '';
    const color = optionBtn.getAttribute('data-color') ?? '';

    if (inputsContainer) {
        inputsContainer.innerHTML = '';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = value;
        input.setAttribute('data-select-input', '');
        inputsContainer.appendChild(input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    root.dispatchEvent(new CustomEvent('ui-select:change', { bubbles: true, detail: { value, label, optionBtn } }));

    if (labelEl) {
        labelEl.textContent = label;
    }

    const colorSwatchEl = root.querySelector('[data-select-color-swatch]');
    if (colorSwatchEl) {
        if (color) {
            colorSwatchEl.style.backgroundColor = color;
            colorSwatchEl.classList.remove('hidden');
        } else {
            colorSwatchEl.classList.add('hidden');
        }
    }

    if (sublabelEl) {
        sublabelEl.textContent = '';
        sublabelEl.classList.add('hidden');
    }

    root.querySelectorAll('[data-select-option]').forEach((btn) => {
        applyOptionButtonStyle(btn, btn === optionBtn);
    });

    closeSelect(root);

    const form = root.closest('form');
    if (form) {
        syncColoringPickerVisibility(form);
    }

    if (root.getAttribute('data-auto-submit') === 'true') {
        if (form) {
            form.submit();
        }
    }
}

function syncColoringPickerVisibility(form) {
    const picker = form.querySelector('[data-coloring-picker]');
    if (!picker) {
        return;
    }

    const serviceSelect = form.querySelector('[data-ui-select][data-input-name="service_ids[]"]');
    if (!serviceSelect) {
        return;
    }

    const selectedOptions = Array.from(serviceSelect.querySelectorAll('[data-select-option][aria-selected="true"]'));
    const hasColoring = selectedOptions.some((btn) => {
        const label = (btn.getAttribute('data-label') || '').toLowerCase();
        return label.includes('color') || label.includes('warna');
    });

    picker.classList.toggle('hidden', !hasColoring);
    picker.querySelectorAll('input[name="hair_color_id"]').forEach((input) => {
        input.disabled = !hasColoring;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
        syncColoringPickerVisibility(form);
    });
});

function updateWalkInSummary(form) {
    const checkboxes = Array.from(form.querySelectorAll('[data-service-checkbox]:checked'));
    const countEl = form.querySelector('[data-summary-count]');
    const durationEl = form.querySelector('[data-summary-duration]');
    const totalEl = form.querySelector('[data-summary-total]');

    let totalPrice = 0;
    let totalDuration = 0;

    checkboxes.forEach((cb) => {
        totalPrice += parseInt(cb.getAttribute('data-price') || '0', 10);
        totalDuration += parseInt(cb.getAttribute('data-duration') || '0', 10);
    });

    if (countEl) {
        countEl.textContent = `${checkboxes.length} Layanan`;
    }
    if (durationEl) {
        durationEl.textContent = `~${totalDuration} menit`;
    }
    if (totalEl) {
        totalEl.textContent = 'Rp' + totalPrice.toLocaleString('id-ID');
    }
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-select-trigger]');
    if (trigger) {
        const root = trigger.closest('[data-ui-select]');
        const panel = root?.querySelector('[data-select-panel]');
        if (root && panel) {
            if (panel.classList.contains('hidden')) {
                openSelect(root);
            } else {
                closeSelect(root);
            }
        }
        return;
    }

    const clearBtn = event.target.closest('[data-select-clear]');
    if (clearBtn) {
        const root = clearBtn.closest('[data-ui-select]');
        const searchInput = root?.querySelector('[data-select-search]');
        if (root && searchInput) {
            searchInput.value = '';
            filterSelectOptions(root, '');
            searchInput.focus();
        }
        return;
    }

    const optionBtn = event.target.closest('[data-select-option]');
    if (optionBtn) {
        const root = optionBtn.closest('[data-ui-select]');
        if (root) {
            selectOption(root, optionBtn);
        }
        return;
    }

    const pageBtn = event.target.closest('[data-page-action]');
    if (pageBtn) {
        const col = pageBtn.closest('[data-queue-column]');
        if (col) {
            const action = pageBtn.getAttribute('data-page-action');
            const totalPages = parseInt(col.getAttribute('data-total-pages') || '1', 10);
            const isSingleMode = col.getAttribute('data-single-mode') === 'true';
            let currentPage = parseInt(col.getAttribute('data-current-page') || '1', 10);

            if (action === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (action === 'next' && currentPage < totalPages) {
                currentPage++;
            }

            col.setAttribute('data-current-page', String(currentPage));

            const hiddenClass = isSingleMode ? 'hidden' : 'md:hidden';
            col.querySelectorAll('[data-queue-card]').forEach((card) => {
                const cardPage = parseInt(card.getAttribute('data-card-page') || '1', 10);
                card.classList.toggle(hiddenClass, cardPage !== currentPage);
            });

            const indicator = col.querySelector('[data-page-indicator]');
            if (indicator) {
                indicator.textContent = `Hal ${currentPage} / ${totalPages}`;
            }

            const prevBtn = col.querySelector('[data-page-action="prev"]');
            const nextBtn = col.querySelector('[data-page-action="next"]');
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages;
            }
        }
        return;
    }

    const pwToggleBtn = event.target.closest('[data-password-toggle]');
    if (pwToggleBtn) {
        if (event.__pwToggled) return;
        event.__pwToggled = true;
        event.preventDefault();
        const container = pwToggleBtn.closest('.relative');
        const input = container?.querySelector('input');
        if (input) {
            const isCurrentlyPassword = input.type === 'password';
            input.type = isCurrentlyPassword ? 'text' : 'password';

            const iconShow = pwToggleBtn.querySelector('[data-icon-show]');
            const iconHide = pwToggleBtn.querySelector('[data-icon-hide]');
            if (iconShow && iconHide) {
                iconShow.classList.toggle('hidden', isCurrentlyPassword);
                iconHide.classList.toggle('hidden', !isCurrentlyPassword);
            }
        }
        return;
    }

    const insideSelect = event.target.closest('[data-ui-select]');
    if (!insideSelect) {
        closeAllSelects();
    }
});

document.addEventListener('change', (event) => {
    const serviceCb = event.target.closest('[data-service-checkbox]');
    if (serviceCb) {
        const form = serviceCb.closest('form');
        if (form) {
            updateWalkInSummary(form);
        }
    }
});

document.addEventListener('input', (event) => {
    const searchInput = event.target.closest('[data-select-search]');
    if (!searchInput) {
        return;
    }

    const root = searchInput.closest('[data-ui-select]');
    if (root) {
        filterSelectOptions(root, searchInput.value);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllSelects();
        if (window.innerWidth >= 768 && window.innerWidth < 1024) {
            document.documentElement.classList.remove('tablet-sidebar-open');
            localStorage.setItem('tablet_sidebar_open', 'false');
        }
        return;
    }

    const searchInput = event.target.closest('[data-select-search]');
    if (searchInput && event.key === 'Enter') {
        event.preventDefault();
        const root = searchInput.closest('[data-ui-select]');
        if (root) {
            const firstVisible = root.querySelector('[data-select-option]:not(.hidden)');
            if (firstVisible) {
                selectOption(root, firstVisible);
            }
        }
    }
});

// Sidebar Toggle Khusus Tablet (768px <= width < 1024px)
function toggleSidebar() {
    if (window.innerWidth >= 768 && window.innerWidth < 1024) {
        const willBeOpen = !document.documentElement.classList.contains('tablet-sidebar-open');
        document.documentElement.classList.toggle('tablet-sidebar-open', willBeOpen);
        try {
            localStorage.setItem('tablet_sidebar_open', willBeOpen ? 'true' : 'false');
        } catch (e) {}
    }
}

window.toggleSidebar = toggleSidebar;

// =========================================================================
// FLASH NOTIFICATION & ALERT AUTO-DISMISS (3 DETIK + GRADIENT SMOOTH EXIT)
// =========================================================================
function dismissFlashAlert(el) {
    if (!el || el.dataset.dismissing === 'true') return;
    el.dataset.dismissing = 'true';

    // Ambil tinggi riil sebelum mulai mengecil agar animasi collapse mulus
    const currentHeight = el.offsetHeight;
    el.style.maxHeight = currentHeight + 'px';

    requestAnimationFrame(() => {
        el.style.transition = 'opacity 0.35s ease-out, transform 0.35s ease-out, max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s ease, margin 0.4s ease, border-width 0.4s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-6px) scale(0.98)';
        el.style.maxHeight = '0px';
        el.style.paddingTop = '0px';
        el.style.paddingBottom = '0px';
        el.style.marginTop = '0px';
        el.style.marginBottom = '0px';
        el.style.borderWidth = '0px';
        el.style.overflow = 'hidden';
    });

    setTimeout(() => {
        const container = el.parentElement;
        el.remove();
        if (container && container.id === 'flash-alert-container' && container.children.length === 0) {
            container.remove();
        }
    }, 420);
}

function initFlashAlert(el) {
    if (!el || el.dataset.initialized === 'true') return;
    el.dataset.initialized = 'true';

    const duration = parseInt(el.dataset.flashDuration, 10) || 3000;
    const bar = el.querySelector('[data-flash-progress]');
    let remaining = duration;
    let startTime = Date.now();
    let timer = null;

    // Animasi progress bar menyusut dari 100% ke 0% selama 3 detik
    if (bar) {
        bar.style.width = '100%';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                bar.style.transition = `width ${duration}ms linear`;
                bar.style.width = '0%';
            });
        });
    }

    const startTimer = () => {
        startTime = Date.now();
        timer = setTimeout(() => {
            dismissFlashAlert(el);
        }, remaining);
    };

    const pauseTimer = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
            remaining = Math.max(0, remaining - (Date.now() - startTime));
            if (bar) {
                const computedWidth = window.getComputedStyle(bar).width;
                bar.style.transition = 'none';
                bar.style.width = computedWidth;
            }
        }
    };

    const resumeTimer = () => {
        if (remaining > 0 && el.dataset.dismissing !== 'true') {
            if (bar) {
                requestAnimationFrame(() => {
                    bar.style.transition = `width ${remaining}ms linear`;
                    bar.style.width = '0%';
                });
            }
            startTimer();
        }
    };

    // Jeda timer jika kursor user berada di atas notifikasi (hover)
    el.addEventListener('mouseenter', pauseTimer);
    el.addEventListener('mouseleave', resumeTimer);

    startTimer();
}

function initAllFlashAlerts() {
    document.querySelectorAll('[data-flash-alert]:not([data-initialized="true"])').forEach((el) => {
        initFlashAlert(el);
    });
}

window.dismissFlashAlert = dismissFlashAlert;
window.initFlashAlert = initFlashAlert;
window.initAllFlashAlerts = initAllFlashAlerts;

// Inisialisasi otomatis pada DOM siap & navigasi Livewire
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllFlashAlerts);
} else {
    initAllFlashAlerts();
}

document.addEventListener('livewire:navigated', initAllFlashAlerts);


