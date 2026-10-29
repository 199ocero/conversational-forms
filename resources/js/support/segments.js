/**
 * Pure helpers for segmented date and time answers, shared by the Alpine
 * component and the tests.
 */

export function pad(value, length) {
    return String(value).padStart(length, '0')
}

/**
 * Build the stored value once every segment is completely filled (DD, MM,
 * YYYY, HH, MM), or `null`. Dates are stored as `YYYY-MM-DD`, times as `HH:MM`.
 */
export function compose(kind, values, segments) {
    if (
        segments.some(
            (segment) => (values[segment.key] ?? '').length !== segment.length,
        )
    ) {
        return null
    }

    if (kind === 'time') {
        return `${pad(values.hour, 2)}:${pad(values.minute, 2)}`
    }

    return `${pad(values.year, 4)}-${pad(values.month, 2)}-${pad(values.day, 2)}`
}

/**
 * Split a stored value back into segment values.
 */
export function decompose(kind, value) {
    if (typeof value !== 'string') {
        return {}
    }

    if (kind === 'time') {
        const match = value.match(/^(\d{2}):(\d{2})/)

        return match ? { hour: match[1], minute: match[2] } : {}
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
