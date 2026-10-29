/**
 * Compare answers loosely: Livewire may send numbers back as strings.
 */
export function isSameValue(a, b) {
    if ([null, undefined].includes(a) || [null, undefined].includes(b)) {
        return a === b
    }

    if (typeof a === 'boolean' || typeof b === 'boolean') {
        return a === b
    }

    return String(a) === String(b)
}

/**
 * Whether typing into this element must never trigger answer shortcuts.
 */
export function isTextEntry(element) {
    if (!(element instanceof HTMLElement)) {
        return false
    }

    if (
        element.isContentEditable ||
        ['TEXTAREA', 'SELECT'].includes(element.tagName)
    ) {
        return true
    }

    return (
        element.tagName === 'INPUT' &&
        ![
            'button',
            'checkbox',
            'color',
            'file',
            'radio',
            'range',
            'reset',
            'submit',
        ].includes(element.type)
    )
}

/**
 * Resolve a typed digit against choices whose keys are numbers, keeping a
 * buffer so multi-digit answers (like 10) can be typed.
 *
 * @returns {{ choice: object|undefined, buffer: string, isAmbiguous: boolean }}
 */
export function getChoiceForDigits(choices, buffer, digit) {
    let candidate = buffer + digit
    let choice = choices.find((item) => item.key === candidate)

    if (!choice) {
        candidate = digit
        choice = choices.find((item) => item.key === candidate)
    }

    if (!choice) {
        return { choice: undefined, buffer: '', isAmbiguous: false }
    }

    const isAmbiguous = choices.some(
        (item) => item.key !== candidate && item.key.startsWith(candidate),
    )

    return { choice, buffer: candidate, isAmbiguous }
}
