import { describe, expect, it } from 'vitest'
import {
    dropIndex,
    move,
    reconcile,
} from '../../resources/js/support/ranking.js'
import {
    compose,
    decompose,
    isValid,
} from '../../resources/js/support/segments.js'

const dateSegments = [
    { key: 'day', length: 2 },
    { key: 'month', length: 2 },
    { key: 'year', length: 4 },
]
const timeSegments = [
    { key: 'hour', length: 2 },
    { key: 'minute', length: 2 },
]

describe('segments', () => {
    it('composes a value once every box is completely filled', () => {
        const date = (day, month, year) =>
            compose('date', { day, month, year }, dateSegments)

        expect(date('05', '06', '2026')).toBe('2026-06-05')
        expect(date('05', '06', '202')).toBeNull()
        expect(date('5', '06', '2026')).toBeNull()
        expect(
            compose('time', { hour: '09', minute: '30' }, timeSegments),
        ).toBe('09:30')
    })

    it('splits a stored value back into boxes', () => {
        expect(decompose('date', '2026-06-05')).toEqual({
            year: '2026',
            month: '06',
            day: '05',
        })
        expect(decompose('time', '09:30')).toEqual({ hour: '09', minute: '30' })
        expect(decompose('date', null)).toEqual({})
    })

    it.each([
        ['date', '2026-02-28', true],
        ['date', '2028-02-29', true],
        ['date', '2026-02-29', false],
        ['date', '2026-13-01', false],
        ['date', '2026-04-31', false],
        ['time', '23:59', true],
        ['time', '24:00', false],
        ['time', '12:60', false],
    ])('%s %s is valid: %s', (kind, value, expected) => {
        expect(isValid(kind, value)).toBe(expected)
    })
})

describe('ranking', () => {
    it('moves items', () => {
        expect(move(['a', 'b', 'c'], 0, 2)).toEqual(['b', 'c', 'a'])
        expect(move(['a', 'b', 'c'], 2, 0)).toEqual(['c', 'a', 'b'])
        expect(move(['a', 'b', 'c'], 1, 5)).toEqual(['a', 'b', 'c'])
    })

    it('keeps a saved order that still matches the options', () => {
        expect(reconcile(['c', 'a', 'b'], ['a', 'b', 'c'])).toEqual([
            'c',
            'a',
            'b',
        ])
        expect(reconcile(['c', 'x'], ['a', 'b', 'c'])).toEqual(['c', 'a', 'b'])
        expect(reconcile(null, ['a', 'b'])).toEqual(['a', 'b'])
    })

    it('finds where a dragged item lands', () => {
        const midpoints = [50, 100, 150]

        expect(dropIndex(midpoints, 10)).toBe(0)
        expect(dropIndex(midpoints, 75)).toBe(1)
        expect(dropIndex(midpoints, 120)).toBe(2)
        expect(dropIndex(midpoints, 400)).toBe(3)
    })
})
