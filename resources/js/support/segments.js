/**
 * Pure helpers for segmented date and time answers, shared by the Alpine
 * component and the tests.
 */

export function pad(value, length) {
    return String(value).padStart(length, '0')
}

const MERIDIEMS = ['am', 'pm']

const isTwelveHour = (segments) =>
    segments.some((segment) => segment.key === 'meridiem')

/**
 * Build the stored value once every segment is completely filled (DD, MM,
 * YYYY, HH, MM, AM / PM), or `null`. Dates are stored as `YYYY-MM-DD`, times
 * as `HH:MM` on a 24-hour clock, also when typed on a 12-hour one.
 */
export function compose(kind, values, segments) {
    if (
        segments.some((segment) =>
            segment.key === 'meridiem'
                ? !MERIDIEMS.includes(values.meridiem)
                : (values[segment.key] ?? '').length !== segment.length,
        )
    ) {
        return null
    }

    if (kind === 'time' && isTwelveHour(segments)) {
        const hour = Number(values.hour)

        // 13 o'clock doesn't exist on a 12-hour clock: keep what was typed,
        // so the server rejects it as an invalid time.
        if (!(hour >= 1 && hour <= 12)) {
            return `${values.hour}:${values.minute} ${values.meridiem}`
        }

        return `${pad((hour % 12) + (values.meridiem === 'pm' ? 12 : 0), 2)}:${pad(values.minute, 2)}`
    }

    if (kind === 'time') {
        return `${pad(values.hour, 2)}:${pad(values.minute, 2)}`
    }

    return `${pad(values.year, 4)}-${pad(values.month, 2)}-${pad(values.day, 2)}`
}

/**
 * Split a stored value back into segment values (on a 12-hour clock when the
 * segments include AM / PM: `21:30` is 09, 30 and PM).
 */
export function decompose(kind, value, segments = []) {
    if (typeof value !== 'string') {
        return {}
    }

    if (kind === 'time') {
        const match = value.match(/^(\d{2}):(\d{2})/)

        if (!match) {
            return {}
        }

        if (!isTwelveHour(segments)) {
            return { hour: match[1], minute: match[2] }
        }

        const hour = Number(match[1])

        return {
            hour: pad(hour % 12 === 0 ? 12 : hour % 12, 2),
            minute: match[2],
            meridiem: hour < 12 ? 'am' : 'pm',
        }
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/)

    return match ? { year: match[1], month: match[2], day: match[3] } : {}
}

/**
 * Whether a composed value is a real date (no 31 February) or time.
 */
export function isValid(kind, value) {
    if (value === null) {
        return false
    }

    if (kind === 'time') {
        const [hour, minute] = value.split(':').map(Number)

        return hour <= 23 && minute <= 59
    }

    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(Date.UTC(year, month - 1, day))

    return (
        year >= 1 &&
        date.getUTCFullYear() === year &&
        date.getUTCMonth() === month - 1 &&
        date.getUTCDate() === day
    )
}

/**
 * Whether a box's option matches what has been typed so far: `3` matches
 * 3, 30 and 31, and `0` matches 01 to 09.
 */
export function matchesTyped(option, typed) {
    if (typed === '') {
        return true
    }

    return (
        option.value.startsWith(typed) ||
        String(Number(option.value)).startsWith(typed)
    )
}

const complete = (value, length) =>
    (value ?? '').length === length ? value : null

const daysInMonth = (year, month) =>
    new Date(Date.UTC(year, month, 0)).getUTCDate()

/**
 * Whether picking `value` for the `key` box can't give a usable date, given
 * the other boxes filled in so far: day 31 in February, or anything before
 * `min` or after `max` (both `YYYY-MM-DD`). Times never disable an option.
 */
export function isDisabledOption(
    kind,
    key,
    value,
    values,
    { min = null, max = null } = {},
) {
    if (kind !== 'date') {
        return false
    }

    const candidate = { ...values, [key]: value }
    const year = complete(candidate.year, 4)
    const month = complete(candidate.month, 2)
    const day = complete(candidate.day, 2)

    // Without a year, allow 29 February (it exists in leap years).
    if (
        month &&
        day &&
        Number(day) > daysInMonth(year ? Number(year) : 2000, Number(month))
    ) {
        return true
    }

    if (!year) {
        return false
    }

    // Compare as precisely as the filled-in boxes allow: a year, a month or a day.
    const prefix = [year, month, day && month ? day : null]
        .filter(Boolean)
        .join('-')

    return (
        (min !== null && prefix < min.slice(0, prefix.length)) ||
        (max !== null && prefix > max.slice(0, prefix.length))
    )
}
