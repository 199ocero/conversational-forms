import { describe, expect, it } from 'vitest'
import {
    getNavigationDirection,
    isAdvanceKey,
    shouldHandleKey,
} from '../../resources/js/keyboard/guard.js'

function keydown(target, key, options = {}) {
    const event = new KeyboardEvent('keydown', {
        key,
        bubbles: true,
        cancelable: true,
        ...options,
    })

    Object.defineProperty(event, 'target', { value: target })

    return event
}

function element(html) {
    document.body.innerHTML = html

    return document.body.querySelector('[data-target]')
}

describe('shouldHandleKey', () => {
    it('handles plain keys on ordinary inputs', () => {
        expect(
            shouldHandleKey(keydown(element('<input data-target>'), 'Enter')),
        ).toBe(true)
    })

    it('ignores keys a field already handled', () => {
        const event = keydown(element('<input data-target>'), 'Enter')
        event.preventDefault()

        expect(shouldHandleKey(event)).toBe(false)
    })

    it('ignores keys during IME composition', () => {
        expect(
            shouldHandleKey(
                keydown(element('<input data-target>'), 'Enter', {
                    isComposing: true,
                }),
            ),
        ).toBe(false)
    })

    it('ignores keys inside an opted-out widget', () => {
        expect(
            shouldHandleKey(
                keydown(
                    element(
                        '<div data-cf-keyboard-ignore><input data-target></div>',
                    ),
                    'Enter',
                ),
            ),
        ).toBe(false)
    })

    it('ignores keys while a popup is expanded', () => {
        expect(
            shouldHandleKey(
                keydown(
                    element(
                        '<input role="combobox" aria-expanded="true" data-target>',
                    ),
                    'Enter',
                ),
            ),
        ).toBe(false)
        expect(
            shouldHandleKey(
                keydown(
                    element(
                        '<input role="combobox" aria-expanded="false" data-target>',
                    ),
                    'Enter',
                ),
            ),
        ).toBe(true)
    })
})

describe('isAdvanceKey', () => {
    it('advances on Enter in a text input', () => {
        expect(
            isAdvanceKey(keydown(element('<input data-target>'), 'Enter')),
        ).toBe(true)
    })

    it.each([
        '<textarea data-target></textarea>',
        '<button data-target></button>',
        '<a href="#" data-target></a>',
        '<select data-target></select>',
        '<div contenteditable="true" data-target></div>',
    ])('leaves the native Enter behaviour of %s alone', (html) => {
        const target = element(html)

        // jsdom does not implement isContentEditable.
        if (target.hasAttribute('contenteditable')) {
            Object.defineProperty(target, 'isContentEditable', { value: true })
        }

        expect(isAdvanceKey(keydown(target, 'Enter'))).toBe(false)
    })

    it('always advances on Ctrl or Cmd + Enter', () => {
        const textarea = element('<textarea data-target></textarea>')

        expect(
            isAdvanceKey(keydown(textarea, 'Enter', { ctrlKey: true })),
        ).toBe(true)
        expect(
            isAdvanceKey(keydown(textarea, 'Enter', { metaKey: true })),
        ).toBe(true)
    })

    it('never advances on Shift + Enter', () => {
        expect(
            isAdvanceKey(
                keydown(element('<input data-target>'), 'Enter', {
                    shiftKey: true,
                }),
            ),
        ).toBe(false)
    })

    it('ignores other keys', () => {
        expect(isAdvanceKey(keydown(element('<input data-target>'), 'a'))).toBe(
            false,
        )
    })
})

describe('getNavigationDirection', () => {
    it('maps Alt + arrows to navigation', () => {
        const target = element('<input data-target>')

        expect(
            getNavigationDirection(
                keydown(target, 'ArrowDown', { altKey: true }),
            ),
        ).toBe('next')
        expect(
            getNavigationDirection(
                keydown(target, 'ArrowUp', { altKey: true }),
            ),
        ).toBe('previous')
    })

    it('leaves plain arrows to fields', () => {
        expect(
            getNavigationDirection(
                keydown(element('<input data-target>'), 'ArrowDown'),
            ),
        ).toBe(null)
    })
})
