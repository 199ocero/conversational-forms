import { describe, expect, it } from 'vitest'
import {
    addDays,
    addMonths,
    clampDate,
    endOfWeek,
    initialDate,
    isMonthOutOfRange,
    monthWeeks,
    startOfWeek,
} from '../../resources/js/support/calendar.js'
import {
    dropIndex,
    move,
    reconcile,
    shiftFor,
} from '../../resources/js/support/ranking.js'
import {
    compose,
    decompose,
    isDisabledOption,
    isValid,
    matchesTyped,
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

describe('12-hour times', () => {
    const segments = [
        { key: 'hour', length: 2 },
        { key: 'minute', length: 2 },
        { key: 'meridiem', length: 2 },
    ]

    it('saves a 24-hour time', () => {
        const time = (hour, minute, meridiem) =>
            compose('time', { hour, minute, meridiem }, segments)

        expect(time('09', '30', 'am')).toBe('09:30')
        expect(time('09', '30', 'pm')).toBe('21:30')
        expect(time('12', '05', 'am')).toBe('00:05')
        expect(time('12', '05', 'pm')).toBe('12:05')
        // Not finished until AM / PM is chosen.
        expect(time('09', '30', undefined)).toBe(null)
        // No 13 o'clock on a 12-hour clock: the server rejects what was typed.
        expect(isValid('time', time('13', '00', 'pm'))).toBe(false)
        expect(isValid('time', time('00', '00', 'am'))).toBe(false)
    })

    it('splits a saved time back onto the 12-hour clock', () => {
        expect(decompose('time', '21:30', segments)).toEqual({
            hour: '09',
            minute: '30',
            meridiem: 'pm',
        })
        expect(decompose('time', '00:05', segments)).toEqual({
            hour: '12',
            minute: '05',
            meridiem: 'am',
        })
        expect(decompose('time', '12:00', segments)).toEqual({
            hour: '12',
            minute: '00',
            meridiem: 'pm',
        })
        expect(decompose('time', '21:30')).toEqual({ hour: '21', minute: '30' })
    })
})

describe('segment lists', () => {
    const option = (value) => ({ value, label: value })

    it('narrows a list to what has been typed', () => {
        const days = ['01', '03', '09', '13', '30', '31'].map(option)
        const matching = (typed) =>
            days
                .filter((day) => matchesTyped(day, typed))
                .map((day) => day.value)

        expect(matching('')).toHaveLength(6)
        expect(matching('3')).toEqual(['03', '30', '31'])
        expect(matching('0')).toEqual(['01', '03', '09'])
        expect(matching('13')).toEqual(['13'])
    })

    it('disables days that a month does not have', () => {
        const disabled = (day, values) =>
            isDisabledOption('date', 'day', day, values)

        expect(disabled('31', { month: '02' })).toBe(true)
        expect(disabled('30', { month: '02' })).toBe(true)
        // 29 February exists in some years, so it stays until a year is known.
        expect(disabled('29', { month: '02' })).toBe(false)
        expect(disabled('29', { month: '02', year: '2025' })).toBe(true)
        expect(disabled('29', { month: '02', year: '2024' })).toBe(false)
        expect(disabled('31', {})).toBe(false)
        // A day already typed rules months out too.
        expect(isDisabledOption('date', 'month', '04', { day: '31' })).toBe(
            true,
        )
    })

    it('disables what falls outside the minimum and maximum dates', () => {
        const range = { min: '2026-03-15', max: '2027-01-31' }
        const disabled = (key, value, values) =>
            isDisabledOption('date', key, value, values, range)

        expect(disabled('year', '2025', {})).toBe(true)
        expect(disabled('year', '2026', {})).toBe(false)
        expect(disabled('month', '02', { year: '2026' })).toBe(true)
        expect(disabled('month', '03', { year: '2026' })).toBe(false)
        expect(disabled('month', '02', { year: '2027' })).toBe(true)
        expect(disabled('day', '14', { year: '2026', month: '03' })).toBe(true)
        expect(disabled('day', '15', { year: '2026', month: '03' })).toBe(false)
        // Without a year, nothing can be ruled out yet.
        expect(disabled('month', '01', {})).toBe(false)
        expect(isDisabledOption('time', 'hour', '23', {}, range)).toBe(false)
    })
})

describe('calendar', () => {
    it('lays a month out in weeks from the first day of the week', () => {
        // 1 October 2026 is a Thursday.
        const sundayFirst = monthWeeks(2026, 10, 0)
        const mondayFirst = monthWeeks(2026, 10, 1)

        expect(sundayFirst[0]).toEqual([
            null,
            null,
            null,
            null,
            '2026-10-01',
            '2026-10-02',
            '2026-10-03',
        ])
        expect(mondayFirst[0].indexOf('2026-10-01')).toBe(3)
        expect(sundayFirst.flat().filter(Boolean)).toHaveLength(31)
        expect(sundayFirst.every((week) => week.length === 7)).toBe(true)
        expect(monthWeeks(2024, 2, 0).flat().filter(Boolean).at(-1)).toBe(
            '2024-02-29',
        )
    })

    it('moves by days, weeks and months', () => {
        expect(addDays('2026-12-31', 1)).toBe('2027-01-01')
        expect(addDays('2026-03-01', -7)).toBe('2026-02-22')
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28')
        expect(addMonths('2024-01-31', 1)).toBe('2024-02-29')
        expect(addMonths('2026-01-15', -1)).toBe('2025-12-15')
        expect(addMonths('2026-06-15', 12)).toBe('2027-06-15')
        // Thursday 15 October 2026.
        expect(startOfWeek('2026-10-15', 1)).toBe('2026-10-12')
        expect(endOfWeek('2026-10-15', 1)).toBe('2026-10-18')
        expect(startOfWeek('2026-10-15', 0)).toBe('2026-10-11')
    })

    it('keeps to the minimum and maximum dates', () => {
        const range = { min: '2026-03-15', max: '2026-12-31' }

        expect(clampDate('2026-01-01', range)).toBe('2026-03-15')
        expect(clampDate('2027-01-01', range)).toBe('2026-12-31')
        expect(clampDate('2026-06-01', range)).toBe('2026-06-01')
        expect(isMonthOutOfRange('2026-02-28', range)).toBe(true)
        expect(isMonthOutOfRange('2026-03-01', range)).toBe(false)
    })

    it('opens on the answer, else on what the boxes hold, else today', () => {
        const today = '2026-10-30'

        expect(
            initialDate({ day: '15', month: '03', year: '2027' }, today),
        ).toBe('2027-03-15')
        expect(initialDate({ year: '1990' }, today)).toBe('1990-10-01')
        expect(initialDate({ month: '02', day: '31' }, today)).toBe(
            '2026-02-28',
        )
        expect(initialDate({}, today)).toBe('2026-10-30')
        expect(initialDate({}, today, { min: '2027-01-01' })).toBe('2027-01-01')
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

    it('slides the rows between the old and new place to make room', () => {
        // Dragging row 0 down to place 2: rows 1 and 2 move up one step.
        expect([0, 1, 2, 3].map((index) => shiftFor(index, 0, 2, 50))).toEqual([
            0, -50, -50, 0,
        ])
        // Dragging row 3 up to place 1: rows 1 and 2 move down one step.
        expect([0, 1, 2, 3].map((index) => shiftFor(index, 3, 1, 50))).toEqual([
            0, 50, 50, 0,
        ])
        // Not moved yet: nothing slides.
        expect([0, 1, 2].map((index) => shiftFor(index, 1, 1, 50))).toEqual([
            0, 0, 0,
        ])
    })
})
