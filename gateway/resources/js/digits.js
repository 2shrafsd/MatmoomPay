// Arabic and Persian keyboards type ٠-٩ and ۰-۹; card numbers, codes and amounts need Latin digits.
const ARABIC_DIGITS = /[٠-٩۰-۹٬٫]/g;
const LATIN = { '٬': ',', '٫': '.' };

export function toLatinDigits(value) {
    return value.replace(ARABIC_DIGITS, (character) => {
        if (LATIN[character]) {
            return LATIN[character];
        }

        const code = character.charCodeAt(0);

        return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
    });
}

// Runs in the capture phase, before Livewire or the page reads the value, on fields that hold numbers.
export function convertDigitsWhileTyping(root = document) {
    root.addEventListener(
        'input',
        (event) => {
            const input = event.target;

            if (!(input instanceof HTMLInputElement) || !['numeric', 'decimal', 'tel'].includes(input.inputMode) && input.type !== 'tel') {
                return;
            }

            const converted = toLatinDigits(input.value);

            if (converted !== input.value) {
                input.value = converted;
            }
        },
        true,
    );
}
