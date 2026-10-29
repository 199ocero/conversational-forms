import { describe, expect, it } from 'vitest'
import { filterInsertion } from '../../resources/js/support/input-filter.js'

const wholeNumber = '^\\d*$'
const decimal = '^-?\\d*(\\.\\d*)?$'
const edit = (value, text, options = {}) => ({
    value,
    selectionStart: value.length,
    selectionEnd: value.length,
    text,
    ...options,
})

describe('filterInsertion', () => {
    it('allows characters that keep the value valid', () => {
        expect(
            filterInsertion({ ...edit('12', '3'), pattern: wholeNumber }),
        ).toBe('3')
        expect(filterInsertion({ ...edit('-1', '.'), pattern: decimal })).toBe(
            '.',
        )
    })

    it('rejects typed characters that would make the value invalid', () => {
        expect(
            filterInsertion({ ...edit('12', 'a'), pattern: wholeNumber }),
        ).toBeNull()
        expect(
            filterInsertion({ ...edit('1.5', '.'), pattern: decimal }),
        ).toBeNull()
        expect(
            filterInsertion({ ...edit('15', '-'), pattern: decimal }),
        ).toBeNull()
    })

    it('checks the value around the selection', () => {
        expect(
            filterInsertion({
                value: '15',
                selectionStart: 0,
                selectionEnd: 0,
                text: '-',
                pattern: decimal,
            }),
        ).toBe('-')
        expect(
            filterInsertion({
                value: '1a5',
                selectionStart: 1,
                selectionEnd: 2,
                text: '0',
                pattern: wholeNumber,
            }),
        ).toBe('0')
    })

    it('keeps the valid characters of pasted text', () => {
        expect(
            filterInsertion({
                ...edit('', '9 people'),
                pattern: wholeNumber,
                isPaste: true,
            }),
        ).toBe('9')
        expect(
            filterInsertion({
                ...edit('', '$1,500.75'),
                pattern: decimal,
                isPaste: true,
            }),
        ).toBe('1500.75')
        expect(
            filterInsertion({
                ...edit('', 'none'),
                pattern: wholeNumber,
                isPaste: true,
            }),
        ).toBeNull()
    })
})
