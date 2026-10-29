/**
 * Decides whether the conversation stage may react to a keydown event.
 *
 * The stage never fights a field: anything a field (or a third-party widget)
 * has already handled, or that belongs to an open popup or an IME, is ignored.
 */
export function shouldHandleKey(event) {
    if (event.defaultPrevented || event.isComposing || event.keyCode === 229) {
        return false
    }

    const target = event.target

    if (!(target instanceof Element)) {
        return true
    }

    if (target.closest('[data-cf-keyboard-ignore]')) {
        return false
    }

    return !target.closest('[aria-expanded="true"]')
}

/**
 * Enter moves to the next step, except where Enter already has a native
 * meaning: buttons and links activate themselves, and multi-line inputs insert
 * a newline. Ctrl / Cmd + Enter always advances.
 */
export function isAdvanceKey(event) {
    if (event.key !== 'Enter') {
        return false
    }

    if (event.metaKey || event.ctrlKey) {
        return true
    }

    if (event.shiftKey || event.altKey) {
        return false
    }

    const target = event.target

    if (!(target instanceof Element)) {
        return true
    }

    if (target instanceof HTMLElement && target.isContentEditable) {
        return false
    }

    return !['TEXTAREA', 'BUTTON', 'A', 'SELECT'].includes(target.tagName)
}

/**
 * Alt + Arrow Down / Up move between steps. Plain arrows are left to fields.
 *
 * @returns {'next' | 'previous' | null}
 */
export function getNavigationDirection(event) {
    if (!event.altKey || event.metaKey || event.ctrlKey || event.shiftKey) {
        return null
    }

    return { ArrowDown: 'next', ArrowUp: 'previous' }[event.key] ?? null
}
