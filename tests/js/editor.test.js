import { describe, expect, it } from 'vitest'
import {
    endings,
    fieldsOf,
    itemAt,
    keyFromLabel,
    moveBefore,
    outline,
    pathOfStatePath,
    previewSequence,
    splitPiping,
    statePathOf,
    stepPathOf,
    tabOfStatePath,
} from '../../resources/js/support/editor.js'
import keys from '../Fixtures/builder/keys.json'

const state = {
    welcome: { enabled: true, title: 'Hi' },
    steps: {
        a: {
            type: 'short_text',
            data: { key: 'name', label: 'Your <b>name</b>?' },
        },
        b: { type: 'statement', data: { key: 'intro', title: 'A word first' } },
        c: {
            type: 'group',
            data: {
                key: 'contact',
                label: 'Contact',
                questions: {
                    d: {
                        type: 'email',
                        data: { key: 'email', label: 'Email' },
                    },
                    e: { type: 'phone', data: { key: '', label: '' } },
                },
            },
        },
        f: { type: 'yes_no', data: { key: 'news', label: 'News, {name}?' } },
    },
    endings: {
        g: { key: 'default', type: 'end_screen', title: 'Thanks!' },
        h: { key: 'away', type: 'redirect', title: null },
    },
}

describe('keys made from labels (shared with PHP)', () => {
    it.each(keys)('$label', ({ label, key }) => {
        expect(keyFromLabel(label)).toBe(key)
    })
})

describe('the outline', () => {
    it('lists steps and group questions in order, numbering all but statements', () => {
        expect(
            outline(state).map(({ path, number, depth, title }) => [
                path,
                number,
                depth,
                title,
            ]),
        ).toEqual([
            ['steps.a', 1, 0, 'Your name?'],
            ['steps.b', null, 0, 'A word first'],
            ['steps.c', 2, 0, 'Contact'],
            ['steps.c.questions.d', null, 1, 'Email'],
            ['steps.c.questions.e', null, 1, ''],
            ['steps.f', 3, 0, 'News, {name}?'],
        ])
    })

    it('flags what is missing', () => {
        const question = outline(state).find(
            (item) => item.path === 'steps.c.questions.e',
        )

        expect(question.isMissingKey).toBe(true)
        expect(question.isMissingTitle).toBe(true)
        expect(
            outline(state).find((item) => item.path === 'steps.a').isMissingKey,
        ).toBe(false)
    })

    it('copes with an empty state', () => {
        expect(outline({})).toEqual([])
        expect(outline({ steps: [] })).toEqual([])
        expect(endings(null)).toEqual([])
    })

    it('lists the endings', () => {
        expect(
            endings(state).map(({ path, type, key }) => [path, type, key]),
        ).toEqual([
            ['endings.g', 'end_screen', 'default'],
            ['endings.h', 'redirect', 'away'],
        ])
    })

    it('walks welcome, steps and endings in the preview', () => {
        expect(previewSequence(state)).toEqual([
            'welcome',
            'steps.a',
            'steps.b',
            'steps.c',
            'steps.f',
            'endings.g',
            'endings.h',
        ])
        expect(
            previewSequence({ ...state, welcome: { enabled: false } })[0],
        ).toBe('steps.a')
    })
})

describe('paths', () => {
    it('finds items by path', () => {
        expect(itemAt(state, 'steps.a').data.key).toBe('name')
        expect(itemAt(state, 'steps.c.questions.d').data.key).toBe('email')
        expect(itemAt(state, 'endings.h').key).toBe('away')
        expect(itemAt(state, 'steps.zzz')).toBeNull()
        expect(itemAt(state, 'design')).toBeNull()
    })

    it('finds the step around a path', () => {
        expect(stepPathOf('steps.c.questions.d')).toBe('steps.c')
        expect(stepPathOf('endings.g')).toBeNull()
    })

    it('maps Livewire state paths, such as error keys, to editor paths', () => {
        expect(pathOfStatePath('data.steps.a.data.label')).toBe('steps.a')
        expect(
            pathOfStatePath(
                'data.steps.c.data.questions.d.data.settings.options.x.label',
            ),
        ).toBe('steps.c.questions.d')
        expect(pathOfStatePath('data.endings.g.url')).toBe('endings.g')
        expect(pathOfStatePath('data.welcome.title')).toBe('welcome')
        expect(pathOfStatePath('data.theme.accent')).toBe('design')
        expect(pathOfStatePath('data.slug')).toBe('settings')
        expect(pathOfStatePath('data.hidden_fields.x.key')).toBe('settings')
    })
})

describe('text edited in the preview', () => {
    it("finds where a field is kept in Livewire's state", () => {
        expect(statePathOf('steps.a', 'label')).toBe('data.steps.a.data.label')
        expect(
            statePathOf('steps.c.questions.d', 'settings.options.x.label'),
        ).toBe('data.steps.c.data.questions.d.data.settings.options.x.label')
        expect(statePathOf('endings.g', 'title')).toBe('data.endings.g.title')
        expect(statePathOf('welcome', 'button_label')).toBe(
            'data.welcome.button_label',
        )
    })

    it("finds the object holding an item's fields", () => {
        expect(fieldsOf(state, 'steps.a').label).toBe('Your <b>name</b>?')
        expect(fieldsOf(state, 'steps.c.questions.d').key).toBe('email')
        expect(fieldsOf(state, 'endings.g').title).toBe('Thanks!')
        expect(fieldsOf(state, 'welcome').title).toBe('Hi')
        expect(fieldsOf(state, 'steps.missing')).toBeNull()
    })
})

describe('tabs', () => {
    it('finds the tab showing a field, such as one with an error', () => {
        expect(tabOfStatePath('data.steps.a.data.label')).toBe('question')
        expect(
            tabOfStatePath('data.steps.a.data.settings.options.x.label'),
        ).toBe('question')
        expect(tabOfStatePath('data.steps.a.data.logic.r.target')).toBe('logic')
        expect(
            tabOfStatePath('data.steps.a.data.visibility.conditions.c.field'),
        ).toBe('logic')
        expect(tabOfStatePath('data.steps.a.data.key')).toBe('more')
        expect(
            tabOfStatePath('data.steps.c.data.questions.d.data.correct.answer'),
        ).toBe('more')
    })
})

describe('piping', () => {
    it('splits text into parts and tokens', () => {
        expect(splitPiping('Hi {name}, you scored {@score}!')).toEqual([
            { text: 'Hi ' },
            { token: 'name' },
            { text: ', you scored ' },
            { token: '@score' },
            { text: '!' },
        ])
        expect(splitPiping('{name}')).toEqual([{ token: 'name' }])
        expect(splitPiping('No {tokens here')).toEqual([
            { text: 'No {tokens here' },
        ])
        expect(splitPiping(null)).toEqual([])
    })
})

describe('reordering', () => {
    it('moves a key before another, or to the end', () => {
        expect(moveBefore(['a', 'b', 'c'], 'c', 'a')).toEqual(['c', 'a', 'b'])
        expect(moveBefore(['a', 'b', 'c'], 'a')).toEqual(['b', 'c', 'a'])
        expect(moveBefore(['a', 'b', 'c'], 'b', 'missing')).toEqual([
            'a',
            'c',
            'b',
        ])
    })
})
