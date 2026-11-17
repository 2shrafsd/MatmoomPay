// Progressive enhancement for the hosted payment pages. The forms work without it.
import { convertDigitsWhileTyping, toLatinDigits } from './digits';

convertDigitsWhileTyping();

const formatters = {
    card(value) {
        return value.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
    },
    expiry(value) {
        const digits = value.replace(/\D/g, '').slice(0, 4);

        return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
    },
    digits(value) {
        return value.replace(/\D/g, '');
    },
};

document.querySelectorAll('[data-format]').forEach((input) => {
    const format = formatters[input.dataset.format];

    if (!format) {
        return;
    }

    input.addEventListener('input', () => {
        input.value = format(toLatinDigits(input.value));
    });
});

document.querySelectorAll('form[data-submit-once]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();

            return;
        }

        form.dataset.submitting = 'true';

        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;

            if (button.dataset.busyLabel) {
                button.textContent = button.dataset.busyLabel;
            }
        });
    });
});

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});
