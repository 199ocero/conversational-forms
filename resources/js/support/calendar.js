/**
 * Pure helpers for the date question's calendar, shared by the Alpine
 * component and the tests. Dates are `YYYY-MM-DD` strings, handled in UTC so
 * the visitor's time zone never shifts a day.
 */
import { isValid, pad } from './segments.js'

function toDate(value) {
    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(0)

    // `setUTCFullYear` keeps years below 100 as they are (`Date.UTC` would not).
    date.setUTCFullYear(year, month - 1, day)

    return date
}

function toDateString(date) {
    return `${pad(date.getUTCFullYear(), 4)}-${pad(date.getUTCMonth() + 1, 2)}-${pad(date.getUTCDate(), 2)}`
}

export function daysInMonth(year, month) {
    const date = new Date(0)

    date.setUTCFullYear(year, month, 0)

    return date.getUTCDate()
}

/**
 * The weeks of a month as rows of seven cells, starting on `firstDayOfWeek`
 * (0 = Sunday). Cells outside the month are `null`.
 */
export function monthWeeks(year, month, firstDayOfWeek = 0) {
    const offset =
        (toDate(`${pad(year, 4)}-${pad(month, 2)}-01`).getUTCDay() -
            firstDayOfWeek +
            7) %
        7
    const cells = Array(offset).fill(null)

    for (let day = 1; day <= daysInMonth(year, month); day++) {
        cells.push(`${pad(year, 4)}-${pad(month, 2)}-${pad(day, 2)}`)
    }

    while (cells.length % 7 !== 0) {
        cells.push(null)
    }

    const weeks = []

    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7))
    }

    return weeks
}

export function addDays(value, days) {
    const date = toDate(value)

    date.setUTCDate(date.getUTCDate() + days)

    return toDateString(date)
}

/**
 * Move by whole months, keeping the day where the month allows it
 * (31 January + 1 month = 28 or 29 February).
 */
export function addMonths(value, months) {
    const [year, month, day] = value.split('-').map(Number)
    const index = year * 12 + (month - 1) + months
    const targetYear = Math.floor(index / 12)
    const targetMonth = (index % 12) + 1

    return `${pad(targetYear, 4)}-${pad(targetMonth, 2)}-${pad(Math.min(day, daysInMonth(targetYear, targetMonth)), 2)}`
}

export function startOfWeek(value, firstDayOfWeek = 0) {
    return addDays(
        value,
        -((toDate(value).getUTCDay() - firstDayOfWeek + 7) % 7),
    )
}

export function endOfWeek(value, firstDayOfWeek = 0) {
    return addDays(startOfWeek(value, firstDayOfWeek), 6)
}

export function isOutOfRange(value, { min = null, max = null } = {}) {
    return (min !== null && value < min) || (max !== null && value > max)
}

export function clampDate(value, { min = null, max = null } = {}) {
    if (min !== null && value < min) {
        return min
    }

    if (max !== null && value > max) {
        return max
    }

    return value
}

/**
 * Whether a whole month lies outside the range, so the calendar can't show it.
 */
export function isMonthOutOfRange(value, { min = null, max = null } = {}) {
    const month = value.slice(0, 7)

    return (
        (min !== null && month < min.slice(0, 7)) ||
        (max !== null && month > max.slice(0, 7))
    )
}

/**
 * The day the calendar opens on: the answer when it is a real date, else as
 * much as the boxes hold (a year, a month), filled in from `today`, and kept
 * within the range.
 */
export function initialDate(values, today, range = {}) {
    const answer = `${values.year ?? ''}-${values.month ?? ''}-${values.day ?? ''}`

    if (/^\d{4}-\d{2}-\d{2}$/.test(answer) && isValid('date', answer)) {
        return clampDate(answer, range)
    }

    const [todayYear, todayMonth, todayDay] = today.split('-').map(Number)
    const year = /^\d{4}$/.test(values.year ?? '')
        ? Number(values.year)
        : todayYear
    const typedMonth = /^\d{2}$/.test(values.month ?? '')
        ? Number(values.month)
        : null
    const month = typedMonth >= 1 && typedMonth <= 12 ? typedMonth : todayMonth
    const typedDay = /^\d{2}$/.test(values.day ?? '')
        ? Number(values.day)
        : null
    const isTodaysMonth = year === todayYear && month === todayMonth
    const day = Math.min(
        typedDay >= 1 ? typedDay : isTodaysMonth ? todayDay : 1,
        daysInMonth(year, month),
    )

    return clampDate(`${pad(year, 4)}-${pad(month, 2)}-${pad(day, 2)}`, range)
}
