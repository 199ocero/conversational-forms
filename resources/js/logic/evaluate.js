/**
 * Condition evaluation in the browser, used for show / hide conditions
 * (`visibleWhen()`). Every rule here mirrors `src/Logic/Values.php`; both run
 * the shared cases in `tests/Fixtures/logic/cases.json`.
 */

const NUMBER = /^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\s*$/
const DATE = /^\d{4}-\d{2}-\d{2}/

export function normalize(value) {
    if (Array.isArray(value)) {
        return value.map(normalize)
    }

    if (value === '' || value === undefined) {
        return null
    }

    return value
}

export function isNumber(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value)
    }

    return typeof value === 'string' && NUMBER.test(value)
}

function text(value) {
    if (value === null) {
        return ''
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false'
    }

    if (typeof value === 'number' || typeof value === 'string') {
        return String(value)
    }

    return ''
}

const lower = (value) => text(value).toLowerCase()

const list = (value) =>
    value === null ? [] : Array.isArray(value) ? value : [value]

const date = (value) =>
    typeof value === 'string' && DATE.test(value) ? value.slice(0, 10) : null

export function equals(a, b) {
    if (
        a === null ||
        b === null ||
        typeof a === 'boolean' ||
        typeof b === 'boolean'
    ) {
        return a === b
    }

    if (isNumber(a) && isNumber(b)) {
        return Number(a) === Number(b)
    }

    return text(a) === text(b)
}

function is(answer, value) {
    return Array.isArray(answer)
        ? answer.some((item) => equals(item, value))
        : equals(answer, value)
}

function isEmpty(answer) {
    return (
        answer === null ||
        (typeof answer === 'string' && answer.trim() === '') ||
        (Array.isArray(answer) && answer.length === 0)
    )
}

const anyText = (answer, test) => list(answer).some((item) => test(lower(item)))

function includesAny(answer, values) {
    const answers = list(answer)

    return list(values).some((value) =>
        answers.some((item) => equals(item, value)),
    )
}

function includesAll(answer, values) {
    values = list(values)

    return values.length > 0 && values.every((value) => is(list(answer), value))
}

function compareNumbers(answer, value, compare) {
    return (
        isNumber(answer) &&
        isNumber(value) &&
        compare(Number(answer), Number(value))
    )
}

function compareDates(answer, value, compare) {
    answer = date(answer)
    value = date(value)

    return answer !== null && value !== null && compare(answer, value)
}

function between(answer, range) {
    if (!Array.isArray(range) || range.length !== 2) {
        return false
    }

    const [min, max] = range

    if (isNumber(answer) && isNumber(min) && isNumber(max)) {
        return Number(answer) >= Number(min) && Number(answer) <= Number(max)
    }

    return (
        compareDates(answer, min, (a, b) => a >= b) &&
        compareDates(answer, max, (a, b) => a <= b)
    )
}

const operators = {
    is: (a, v) => is(a, v),
    is_not: (a, v) => !is(a, v),
    is_empty: (a) => isEmpty(a),
    is_not_empty: (a) => !isEmpty(a),
    contains: (a, v) => anyText(a, (t) => t.includes(lower(v))),
    not_contains: (a, v) => !anyText(a, (t) => t.includes(lower(v))),
    starts_with: (a, v) => anyText(a, (t) => t.startsWith(lower(v))),
    ends_with: (a, v) => anyText(a, (t) => t.endsWith(lower(v))),
    includes_any: (a, v) => includesAny(a, v),
    includes_all: (a, v) => includesAll(a, v),
    excludes: (a, v) => !includesAny(a, v),
    gt: (a, v) => compareNumbers(a, v, (x, y) => x > y),
    gte: (a, v) => compareNumbers(a, v, (x, y) => x >= y),
    lt: (a, v) => compareNumbers(a, v, (x, y) => x < y),
    lte: (a, v) => compareNumbers(a, v, (x, y) => x <= y),
    between: (a, v) => between(a, v),
    before: (a, v) => compareDates(a, v, (x, y) => x < y),
    after: (a, v) => compareDates(a, v, (x, y) => x > y),
    on: (a, v) => compareDates(a, v, (x, y) => x === y),
    not_on: (a, v) => compareDates(a, v, (x, y) => x !== y),
}

export function evaluateOperator(operator, answer, value) {
    const evaluate = operators[operator]

    if (!evaluate) {
        throw new Error(`The logic operator [${operator}] is not supported.`)
    }

    return evaluate(normalize(answer), normalize(value))
}

/**
 * @param {object} conditions `{ all: [...] }`, `{ any: [...] }` or `{ field, op, value }`.
 * @param {(field: string) => any} getAnswer
 * @param {object} variables
 */
export function matches(conditions, getAnswer, variables = {}) {
    if (conditions.all) {
        return conditions.all.every((item) =>
            matches(item, getAnswer, variables),
        )
    }

    if (conditions.any) {
        return conditions.any.some((item) =>
            matches(item, getAnswer, variables),
        )
    }

    const subject = conditions.field.startsWith('@')
        ? variables[conditions.field.slice(1)]
        : getAnswer(conditions.field)

    return evaluateOperator(
        conditions.op,
        subject ?? null,
        conditions.value ?? null,
    )
}
