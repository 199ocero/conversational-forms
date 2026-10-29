import { describe, expect, it } from 'vitest'
import { evaluateOperator, matches } from '../../resources/js/logic/evaluate.js'
import cases from '../fixtures/logic/cases.json'

const { operators, groups } = cases

const get = (answers) => (field) =>
    field
        .split('.')
        .reduce(
            (value, key) => (value == null ? undefined : value[key]),
            answers,
        )

describe('operators (shared with PHP)', () => {
    it.each(operators)('$op: $note', ({ op, answer, value, expected }) => {
        expect(evaluateOperator(op, answer, value)).toBe(expected)
    })
})

describe('condition groups (shared with PHP)', () => {
    it.each(groups)('$note', ({ conditions, answers, variables, expected }) => {
        expect(matches(conditions, get(answers), variables)).toBe(expected)
    })
})

it('rejects unknown operators', () => {
    expect(() => evaluateOperator('roughly', 'a', 'b')).toThrow(
        '[roughly] is not supported',
    )
})
