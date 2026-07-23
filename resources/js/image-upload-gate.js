const MAX_BYTES_DEFAULT = 20480 * 1024;
const MAX_FILES_DEFAULT = 8;

function matchesUploadProperty(event, property) {
    return event?.detail?.property === property;
}

export function validateSelectedFiles(input) {
    const maxBytes = Number(input.dataset.maxBytes || MAX_BYTES_DEFAULT);
    const maxFiles = Number(input.dataset.maxFiles || MAX_FILES_DEFAULT);
    const files = Array.from(input.files || []);

    if (files.length > maxFiles) {
        window.alert(`حداکثر ${maxFiles} تصویر می‌توانید انتخاب کنید.`);
        input.value = '';
        return false;
    }

    for (const file of files) {
        if (file.size > maxBytes) {
            window.alert('حجم هر تصویر نباید بیشتر از ۲۰ مگابایت باشد.');
            input.value = '';
            return false;
        }
    }

    return true;
}

function registerImageUploadGate() {
    const Alpine = window.Alpine;

    if (! Alpine) {
        return;
    }

    Alpine.data('imageUploadGate', (property) => ({
        uploadsInFlight: 0,

        init() {
            this.onUploadStart = (event) => {
                if (matchesUploadProperty(event, property)) {
                    this.uploadsInFlight++;
                }
            };

            this.onUploadFinish = (event) => {
                if (matchesUploadProperty(event, property)) {
                    this.uploadsInFlight = Math.max(0, this.uploadsInFlight - 1);
                }
            };

            window.addEventListener('livewire-upload-start', this.onUploadStart);
            window.addEventListener('livewire-upload-finish', this.onUploadFinish);
            window.addEventListener('livewire-upload-error', this.onUploadFinish);
            window.addEventListener('livewire-upload-cancel', this.onUploadFinish);
        },

        destroy() {
            window.removeEventListener('livewire-upload-start', this.onUploadStart);
            window.removeEventListener('livewire-upload-finish', this.onUploadFinish);
            window.removeEventListener('livewire-upload-error', this.onUploadFinish);
            window.removeEventListener('livewire-upload-cancel', this.onUploadFinish);
        },
    }));
}

if (window.Alpine) {
    registerImageUploadGate();
} else {
    document.addEventListener('alpine:init', registerImageUploadGate);
}

document.addEventListener('livewire:init', registerImageUploadGate);

document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }

    if (! input.closest('[data-image-upload-panel], [data-room-type-form], .image-upload-html-field')) {
        return;
    }

    validateSelectedFiles(input);
});

export function bindFormSubmitPending(form) {
    if (! form || form.dataset.submitPendingBound === '1') {
        return;
    }

    form.dataset.submitPendingBound = '1';

    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');

        if (! button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.dataset.originalLabel = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>در حال ذخیره…';
    });
}
