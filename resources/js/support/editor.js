/**
 * Pure helpers for the builder's editor: the outline of a conversation's
 * builder state, question numbers, keys made from labels and piping tokens.
 *
 * Items are addressed by a path through the state: `steps.{uuid}`,
 * `steps.{uuid}.questions.{uuid}` for a question in a group,
 * `endings.{uuid}`, and `welcome`, `design` and `settings`.
 */

const TRANSLITERATIONS = {
    ß: 'ss',
    æ: 'ae',
    Æ: 'AE',
    ø: 'o',
    Ø: 'O',
    œ: 'oe',
    Œ: 'OE',
    đ: 'd',
    Đ: 'D',
    ł: 'l',
    Ł: 'L',
    þ: 'th',
    Þ: 'TH',
}

/**
 * Like Laravel's `Str::ascii()` for the characters labels usually contain.
 */
function ascii(value) {
    return value
        .replace(/[ßæÆøØœŒđĐłŁþÞ]/g, (character) => TRANSLITERATIONS[character])
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^\x00-\x7f]/g, '')
}

/**
 * Like Laravel's `Str::snake()`.
 */
function snake(value) {
    if (/^[a-z]+$/.test(value)) {
        return value
    }

    return value
        .replace(
            /(^|[ \t\r\n\f\v])([a-z])/g,
            (match, space, letter) => space + letter.toUpperCase(),
        )
        .replace(/\s+/gu, '')
        .replace(/(.)(?=[A-Z])/gu, '$1_')
        .toLowerCase()
}

/**
 * A key made from a label: "What's your name?" becomes `whats_your_name`.
 * Mirrors `DefinitionState::keyFromLabel()`.
 */
export function keyFromLabel(label) {
    let key = snake(
        ascii(
            String(label ?? '')
                .replace(/<[a-zA-Z\/!?][^>]*>?/g, '')
                .replace(/\{[^}]*\}/g, ''),
        ),
    )
        .replace(/[^a-z0-9_]/g, '')
        .replace(/^_+|_+$/g, '')
        .slice(0, 40)

    if (/^[a-z]/.test(key)) {
        return key.replace(/_+$/, '')
    }

    return 'question' + (key === '' ? '' : `_${key}`)
}

/**
 * Split text into plain parts and piping tokens (`{name}`, `{@score}`), so
 * the preview can show tokens without ever rendering text as HTML.
 */
export function splitPiping(text) {
    const parts = []
    const pattern = /\{(@?[A-Za-z_][\w.]*)\}/g
    const value = String(text ?? '')
    let last = 0

    for (const match of value.matchAll(pattern)) {
        if (match.index > last) {
            parts.push({ text: value.slice(last, match.index) })
        }

        parts.push({ token: match[1] })
        last = match.index + match[0].length
    }

    if (last < value.length) {
        parts.push({ text: value.slice(last) })
    }

    return parts
}

/**
 * The text of a step as one line, for the outline.
 */
export function stepTitle(step) {
    const data = step?.data ?? {}
    const text = step?.type === 'statement' ? data.title : data.label

    return String(text ?? '')
        .replace(/<[a-zA-Z\/!?][^>]*>?/g, '')
        .trim()
}

function entries(items) {
    return Object.entries(items && typeof items === 'object' ? items : {})
}

/**
 * Everything in the editor's outline, in order, with question numbers and
 * what is still missing. Statements aren't numbered; a group is numbered as
 * one step, like in the conversation itself.
 */
export function outline(state) {
    const items = []
    let number = 0

    for (const [uuid, step] of entries(state?.steps)) {
        const path = `steps.${uuid}`
        const isStatement = step?.type === 'statement'
        const isGroup = step?.type === 'group'

        items.push({
            path,
            kind: 'step',
            type: step?.type ?? null,
            key: step?.data?.key ?? null,
            title: stepTitle(step),
            number: isStatement ? null : ++number,
            depth: 0,
            parent: null,
            isMissingKey: !step?.data?.key,
            isMissingTitle: !isGroup && stepTitle(step) === '',
        })

        if (!isGroup) {
            continue
        }

        for (const [questionUuid, question] of entries(step?.data?.questions)) {
            items.push({
                path: `${path}.questions.${questionUuid}`,
                kind: 'question',
                type: question?.type ?? null,
                key: question?.data?.key ?? null,
                title: stepTitle(question),
                number: null,
                depth: 1,
                parent: path,
                isMissingKey: !question?.data?.key,
                isMissingTitle: stepTitle(question) === '',
            })
        }
    }

    return items
}

/**
 * The endings, with their keys and titles.
 */
export function endings(state) {
    return entries(state?.endings).map(([uuid, ending]) => ({
        path: `endings.${uuid}`,
        kind: 'ending',
        type: ending?.type ?? 'end_screen',
        key: ending?.key ?? null,
        title: String(ending?.title ?? '').trim(),
        isMissingKey: !ending?.key,
        isMissingTitle: false,
    }))
}

/**
 * Find a step, a question in a group or an ending by its path.
 */
export function itemAt(state, path) {
    const segments = String(path ?? '').split('.')

    if (segments[0] === 'endings') {
        return state?.endings?.[segments[1]] ?? null
    }

    if (segments[0] !== 'steps') {
        return null
    }

    const step = state?.steps?.[segments[1]] ?? null

    if (segments[2] === 'questions') {
        return step?.data?.questions?.[segments[3]] ?? null
    }

    return step
}

/**
 * The path of the top-level step that contains a path.
 */
export function stepPathOf(path) {
    const segments = String(path ?? '').split('.')

    return segments[0] === 'steps' && segments[1]
        ? `steps.${segments[1]}`
        : null
}

/**
 * The editor path of a state path from Livewire, such as an error key:
 * `data.steps.abc.data.questions.def.data.label` belongs to
 * `steps.abc.questions.def`, and `data.theme.accent` to `design`.
 */
export function pathOfStatePath(statePath) {
    const segments = String(statePath ?? '')
        .replace(/^data\./, '')
        .split('.')

    switch (segments[0]) {
        case 'steps':
            if (segments[3] === 'questions' && segments[4]) {
                return `steps.${segments[1]}.questions.${segments[4]}`
            }

            return segments[1] ? `steps.${segments[1]}` : null
        case 'endings':
            return segments[1] ? `endings.${segments[1]}` : 'endings'
        case 'welcome':
            return 'welcome'
        case 'theme':
            return 'design'
        default:
            return 'settings'
    }
}

/**
 * Where a field of an item is kept in Livewire's state: `label` of
 * `steps.{uuid}` is `data.steps.{uuid}.data.label`. Endings and the welcome
 * screen keep their fields directly.
 */
export function statePathOf(path, field) {
    const segments = String(path ?? '').split('.')

    if (path === 'welcome') {
        return `data.welcome.${field}`
    }

    if (segments[0] === 'endings') {
        return `data.endings.${segments[1]}.${field}`
    }

    if (segments[2] === 'questions') {
        return `data.steps.${segments[1]}.data.questions.${segments[3]}.data.${field}`
    }

    return `data.steps.${segments[1]}.data.${field}`
}

/**
 * The object holding an item's fields, so they can be read and written in
 * place: a step's `data`, an ending, or the welcome screen.
 */
export function fieldsOf(state, path) {
    if (path === 'welcome') {
        return state?.welcome ?? null
    }

    const item = itemAt(state, path)

    return String(path ?? '').startsWith('endings.')
        ? item
        : (item?.data ?? null)
}

/**
 * The editor tab showing a state path, such as an error key: conditions
 * and rules are on `logic`, keys and quiz scoring on `more`, and everything
 * else on `question`.
 */
export function tabOfStatePath(statePath) {
    const path = String(statePath ?? '')

    if (/\.data\.(visibility|logic)(\.|$)/.test(path)) {
        return 'logic'
    }

    if (/\.data\.(key|correct)(\.|$)/.test(path)) {
        return 'more'
    }

    return 'question'
}

/**
 * The previewable paths in order: welcome (when shown), every step, then
 * the endings. Prev / Next in the preview walk this list.
 */
export function previewSequence(state) {
    return [
        ...(state?.welcome?.enabled ? ['welcome'] : []),
        ...outline(state)
            .filter((item) => item.kind === 'step')
            .map((item) => item.path),
        ...endings(state).map((item) => item.path),
    ]
}

/**
 * The list after moving `moved` before `before` (or to the end).
 */
export function moveBefore(keys, moved, before = null) {
    const list = keys.filter((key) => key !== moved)
    const index = before === null ? -1 : list.indexOf(before)

    list.splice(index === -1 ? list.length : index, 0, moved)

    return list
}
