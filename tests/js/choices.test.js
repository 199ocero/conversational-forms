import { describe, expect, it } from 'vitest'
import {
    getChoiceForDigits,
    isSameValue,
    isTextEntry,
} from '../../resources/js/support/choices.js'

const scale = Array.from({ length: 11 }, (_, value) => ({
    value,
    key: String(value),
}))

describe('isSameValue', () => {
    it('compares numbers and numeric strings loosely', () => {
        expect(isSameValue(7, '7')).toBe(true)
        expect(isSameValue('enterprise', 'enterprise')).toBe(true)
        expect(isSameValue(7, 8)).toBe(false)
    })

    it('never treats false or zero as unanswered', () => {
        expect(isSameValue(false, null)).toBe(false)
        expect(isSameValue(0, null)).toBe(false)
        expect(isSameValue(false, 'false')).toBe(false)
        expect(isSameValue(true, true)).toBe(true)
    })
})

describe('isTextEntry', () => {
    it.each([
        ['<input type="text">', true],
        ['<input type="email">', true],
        ['<textarea></textarea>', true],
        ['<select></select>', true],
        ['<input type="checkbox">', false],
        ['<button></button>', false],
        ['<div></div>', false],
    ])('%s is text entry: %s', (html, expected) => {
        document.body.innerHTML = html

        expect(isTextEntry(document.body.firstElementChild)).toBe(expected)
    })
})

describe('getChoiceForDigits', () => {
    it('selects a single digit immediately when no longer answer starts with it', () => {
        expect(getChoiceForDigits(scale, '', '7')).toEqual({
            choice: scale[7],
            buffer: '7',
            isAmbiguous: false,
        })
    })

    it('waits when a longer answer could still be typed', () => {
        expect(getChoiceForDigits(scale, '', '1')).toEqual({
            choice: scale[1],
            buffer: '1',
            isAmbiguous: true,
        })
    })

    it('combines buffered digits into a longer answer', () => {
        expect(getChoiceForDigits(scale, '1', '0')).toEqual({
            choice: scale[10],
            buffer: '10',
            isAmbiguous: false,
        })
    })

    it('starts over when the buffered digits do not form an answer', () => {
        expect(getChoiceForDigits(scale, '7', '3')).toEqual({
            choice: scale[3],
            buffer: '3',
            isAmbiguous: false,
        })
    })

    it('ignores digits outside the scale', () => {
        const oneToFive = scale.slice(1, 6)

        expect(getChoiceForDigits(oneToFive, '', '9')).toEqual({
            choice: undefined,
            buffer: '',
            isAmbiguous: false,
        })
    })
})
