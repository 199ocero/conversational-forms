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
} from '../support/editor.js'
import {
    contrastIssues,
    cssVariables,
    fontStylesheetUrl,
    formatRatio,
    isDark,
    themeFrom,
} from '../support/theme.js'

const FIXED_PATHS = ['welcome', 'design', 'settings']

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'

/**
 * The builder's editor: an outline of the conversation, a live preview and
 * the settings of the selected item.
 *
 * Everything is read from `$wire.data`, which Filament's fields change in the
 * browser as people type, so the outline and the preview follow every
 * keystroke without a request. Only adding, copying, deleting and reordering
 * go to the server, through the builder's own actions.
 */
export default function builderEditorComponent({
    configuration,
    translations,
}) {
    let savedHash = null
    let lastChangeHash = null
    let autosaveTimer = null
    let savedKeys = new Map()

    return {
        pane: 'preview',

        isPanelOpen: false,

        device: 'desktop',

        previewPath: null,

        picker: null,

        pickerQuery: '',

        confirmingDelete: null,

        dragging: null,

        dropTarget: null,

        isBusy: false,

        errorPaths: [],

        errorFields: [],

        answers: {},

        init() {
            const store = {
                selected: null,
                view: 'content',
                isDirty: false,
                isSaving: false,
                saveProblem: null,
                is(path) {
                    return this.selected === path
                },
                isWithin(path) {
                    return (
                        this.selected === path ||
                        String(this.selected ?? '').startsWith(`${path}.`)
                    )
                },
                // The settings shown: the selected item's, or in the logic
                // view every step's.
                shows(path) {
                    return this.view === 'logic'
                        ? String(path).startsWith('steps.')
                        : this.is(path)
                },
                showsWithin(path) {
                    return this.view === 'logic'
                        ? String(path).startsWith('steps.')
                        : this.isWithin(path)
                },
            }

            window.Alpine.store('cfEditor', store)

            window.conversationalFormsEditor = {
                followKey: (...args) => this.followKey(...args),
            }

            this.rememberSaved()

            this.select(this.pathFromHash() ?? this.firstPath(), {
                updatesHash: false,
            })

            window.Alpine.effect(() => {
                const hash = JSON.stringify(this.$wire.data)
                const isDirty = hash !== savedHash

                window.Alpine.store('cfEditor').isDirty = isDirty

                // Every change starts the countdown again, so saving waits for a pause.
                if (isDirty && hash !== lastChangeHash) {
                    lastChangeHash = hash
                    this.scheduleAutosave()
                }
            })

            // Keep the selection on something that still exists after a request.
            window.Alpine.effect(() => {
                const selected = window.Alpine.store('cfEditor').selected

                if (!this.exists(selected)) {
                    queueMicrotask(() => this.select(this.firstPath()))
                }
            })

            this.$wire.$on('cf-editor-saved', () => {
                this.errorPaths = []
                this.errorFields = []
                window.Alpine.store('cfEditor').saveProblem = null
                this.$nextTick(() => this.rememberSaved())
            })

            this.$wire.$on('cf-editor-autosave-failed', ({ message }) => {
                window.Alpine.store('cfEditor').saveProblem = message
            })

            this.$wire.$on('cf-editor-invalid', ({ paths, isAutosave }) => {
                this.errorFields = paths ?? []
                this.errorPaths = [
                    ...new Set(this.errorFields.map(pathOfStatePath)),
                ]

                if (!this.errorPaths.length) {
                    return
                }

                // Saving by itself never moves people away from what they're doing:
                // the steps that need attention are only marked in the outline.
                if (isAutosave) {
                    window.Alpine.store('cfEditor').saveProblem =
                        translations.needs_attention

                    return
                }

                this.select(this.errorPaths[0])

                // Text is fixed in the preview, logic in the logic view, the rest in the settings.
                if (tabOfStatePath(paths[0]) === 'logic') {
                    this.setView('logic')
                } else if (
                    !/\.(label|title|description|button_label)$/.test(paths[0])
                ) {
                    this.openPanel()
                }
            })

            this.onHashChange = () => {
                const path = this.pathFromHash()

                if (path && path !== this.selected) {
                    this.select(path, { updatesHash: false })
                }
            }

            window.addEventListener('hashchange', this.onHashChange)
        },

        destroy() {
            clearTimeout(autosaveTimer)

            window.removeEventListener('hashchange', this.onHashChange)
        },

        get state() {
            return this.$wire.data ?? {}
        },

        get selected() {
            return window.Alpine.store('cfEditor').selected
        },

        get settings() {
            return this.state.settings ?? {}
        },

        // The outline.

        get outlineItems() {
            return outline(this.state)
        },

        get endingItems() {
            return endings(this.state)
        },

        get isEmpty() {
            return this.outlineItems.length === 0
        },

        rowClasses(path) {
            return {
                'cf-active': this.selected === path,
                'cf-editor-row-within': this.selected?.startsWith(`${path}.`),
                'cf-editor-row-invalid': this.hasError(path),
                'cf-editor-row-dragging': this.dragging?.path === path,
                'cf-editor-row-drop': this.dropTarget === path,
            }
        },

        hasError(path) {
            return this.errorPaths.includes(path)
        },

        needsAttention(item) {
            return (
                this.hasError(item.path) ||
                item.isMissingKey ||
                item.isMissingTitle
            )
        },

        typeLabel(type) {
            return configuration.types[type]?.label ?? type
        },

        typeIcon(type) {
            return configuration.types[type]?.icon ?? ''
        },

        endingIcon(type) {
            return (
                configuration.endingIcons[type] ??
                configuration.endingIcons.end_screen
            )
        },

        isConditional(path) {
            const conditions = itemAt(this.state, path)?.data?.visibility

            return (
                Boolean(conditions?.raw) ||
                Object.keys(conditions?.conditions ?? {}).length > 0
            )
        },

        hasLogic(path) {
            return (
                Object.keys(itemAt(this.state, path)?.data?.logic ?? {})
                    .length > 0
            )
        },

        // Selecting.

        select(path, { updatesHash = true } = {}) {
            if (!path) {
                return
            }

            this.confirmingDelete = null
            window.Alpine.store('cfEditor').selected = path

            if (!FIXED_PATHS.slice(1).includes(path)) {
                this.previewPath = stepPathOf(path) ?? path
            }

            // Design and settings have no preview of their own: they open the settings panel.
            if (FIXED_PATHS.slice(1).includes(path)) {
                this.setView('content')
                this.openPanel()
            }

            if (updatesHash && window.location.hash !== `#${path}`) {
                history.replaceState(null, '', `#${path}`)
            }

            if (this.view === 'logic') {
                this.$nextTick(() => this.scrollToLogic(path))
            }

            if (
                window.matchMedia('(max-width: 63.999rem)').matches &&
                this.pane === 'outline'
            ) {
                this.pane = 'preview'
            }
        },

        pathFromHash() {
            const path = decodeURIComponent(window.location.hash.slice(1))

            return this.exists(path) ? path : null
        },

        exists(path) {
            if (!path) {
                return false
            }

            return (
                FIXED_PATHS.includes(path) || itemAt(this.state, path) !== null
            )
        },

        firstPath() {
            return (
                this.outlineItems[0]?.path ??
                (this.state.welcome?.enabled ? 'welcome' : 'settings')
            )
        },

        // Views: the content (the preview, with settings in a panel opened
        // on demand) and the logic of every step.

        get view() {
            return window.Alpine.store('cfEditor').view
        },

        setView(view) {
            window.Alpine.store('cfEditor').view = view

            if (view === 'logic') {
                this.isPanelOpen = false
                this.$nextTick(() => this.scrollToLogic(this.selected))

                // Text and keys typed since the last request reach the server, so the
                // logic's lists of questions, steps and endings include them.
                this.$wire.$refresh()
            }
        },

        scrollToLogic(path) {
            const step = stepPathOf(path) ?? path

            this.scrollPanelTo(
                this.$root.querySelector(
                    `[data-cf-editor-item="${CSS.escape(step ?? '')}"]`,
                ),
            )
        },

        openPanel(section = null) {
            this.isPanelOpen = true

            if (section) {
                this.$nextTick(() =>
                    this.scrollPanelTo(
                        this.$root.querySelector(
                            `[data-cf-editor-item="${CSS.escape(this.selected)}"] [data-cf-editor-${section}]`,
                        ),
                    ),
                )
            }
        },

        /**
         * Scroll the panel (and nothing around it) to an element. Unlike
         * `scrollIntoView()`, this never scrolls the page's own containers,
         * which would push the whole editor out of the window.
         */
        scrollPanelTo(element) {
            const container = this.$root.querySelector(
                this.view === 'logic'
                    ? '.cf-editor-panel'
                    : '.cf-editor-panel-body',
            )

            if (!element || !container) {
                return
            }

            const top =
                element.getBoundingClientRect().top -
                container.getBoundingClientRect().top +
                container.scrollTop -
                16

            container.scrollTo({
                top: Math.max(0, top),
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                    .matches
                    ? 'auto'
                    : 'smooth',
            })
        },

        closePanel() {
            this.isPanelOpen = false
        },

        /**
         * A click anywhere else closes the settings panel, except on what
         * opens it (so that click doesn't close it again), and on Filament's
         * dialogs and dropdowns, which are drawn outside the panel.
         */
        closePanelFromOutside(event) {
            if (!this.isPanelOpen || this.view === 'logic') {
                return
            }

            if (
                event.target.closest?.(
                    '[data-cf-editor-opens-panel], .fi-modal, .fi-dropdown-panel, .fi-no-notification',
                )
            ) {
                return
            }

            this.closePanel()
        },

        // The toolbar above the selected step in the preview.

        get toolbarPath() {
            const path = this.selected

            if (!path || ['design', 'settings'].includes(path)) {
                return null
            }

            return path
        },

        get toolbarItem() {
            return itemAt(this.state, this.toolbarPath)
        },

        get isWelcomeShown() {
            return Boolean(this.state.welcome?.enabled)
        },

        toggleWelcome() {
            if (this.state.welcome) {
                this.state.welcome.enabled = !this.state.welcome.enabled
            }
        },

        itemType(path) {
            return itemAt(this.state, path)?.type ?? null
        },

        titleOf(path) {
            return (
                this.outlineItems.find((item) => item.path === path)?.title ||
                translations.untitled
            )
        },

        hasLogicAt(path) {
            return this.isConditional(path) || this.hasLogic(path)
        },

        // Choices are added, named and removed in the preview.

        addOption(question, name = 'options') {
            const settings = fieldsOf(this.state, question.path)?.settings

            if (!settings) {
                return
            }

            const uuid = crypto.randomUUID()
            const option = { label: '', value: '' }

            if (question.type === 'picture_choice') {
                option.image = null
            }

            // An empty list arrives from PHP as an array; options are keyed by UUID.
            settings[name] = {
                ...(Array.isArray(settings[name]) ? {} : settings[name]),
                [uuid]: option,
            }

            this.$nextTick(() =>
                this.$root
                    .querySelector(
                        `[data-cf-editor-option="${CSS.escape(uuid)}"] [role=button]`,
                    )
                    ?.click(),
            )
        },

        removeOption(question, uuid, name = 'options') {
            const settings = fieldsOf(this.state, question.path)?.settings

            if (!settings?.[name] || Object.keys(settings[name]).length <= 1) {
                return
            }

            const options = { ...settings[name] }

            delete options[uuid]

            settings[name] = options
        },

        get selectionTitle() {
            const path = this.selected

            if (FIXED_PATHS.includes(path)) {
                return translations[path]
            }

            if (path?.startsWith('endings.')) {
                return translations.ending
            }

            const item = itemAt(this.state, path)

            return item ? this.typeLabel(item.type) : ''
        },

        get selectionIcon() {
            const item = itemAt(this.state, this.selected)

            if (this.selected?.startsWith('endings.')) {
                return this.endingIcon(item?.type)
            }

            return item ? this.typeIcon(item.type) : ''
        },

        get selectedGroupPath() {
            const step = stepPathOf(this.selected)

            return step && itemAt(this.state, step)?.type === 'group'
                ? step
                : null
        },

        handleKeydown(event) {
            const target = event.target

            if (!target.closest?.('[data-cf-editor-outline]')) {
                return
            }

            if (!['ArrowUp', 'ArrowDown'].includes(event.key)) {
                return
            }

            const rows = [
                ...this.$root.querySelectorAll('[data-cf-editor-row]'),
            ]
            const index = rows.indexOf(target.closest('[data-cf-editor-row]'))

            if (index === -1) {
                return
            }

            event.preventDefault()

            if (event.altKey) {
                this.moveSelected(event.key === 'ArrowUp' ? -1 : 1)

                return
            }

            const next = rows[index + (event.key === 'ArrowUp' ? -1 : 1)]

            next?.focus()
            next?.click()
        },

        // Keys follow the text of new steps.

        rememberSaved() {
            // What the server holds now: changes typed while a save was on its
            // way haven't reached it, so they still count as unsaved.
            savedHash = JSON.stringify(
                this.$wire.__instance?.canonical?.data ?? this.$wire.data,
            )
            savedKeys = new Map()

            for (const item of [...this.outlineItems, ...this.endingItems]) {
                savedKeys.set(item.path, item.key)
            }

            const isDirty = JSON.stringify(this.$wire.data) !== savedHash

            window.Alpine.store('cfEditor').isDirty = isDirty

            if (isDirty) {
                this.scheduleAutosave()
            }
        },

        // Saving by itself, a few seconds after the last change.

        scheduleAutosave() {
            const seconds = configuration.autosaveSeconds

            if (!seconds) {
                return
            }

            clearTimeout(autosaveTimer)
            autosaveTimer = setTimeout(() => this.autosave(), seconds * 1000)
        },

        async autosave() {
            const store = window.Alpine.store('cfEditor')

            if (!store.isDirty || store.isSaving) {
                return
            }

            // Wait for adding, copying, deleting or reordering to finish.
            if (this.isBusy) {
                this.scheduleAutosave()

                return
            }

            store.isSaving = true

            try {
                await this.$wire.autosave()
            } finally {
                store.isSaving = false
            }
        },

        /**
         * Called by the text fields of steps (see `BuilderSchema`): while a
         * step's key is empty or was made from its text, the key follows the
         * text. Saved keys never change, so logic and answers keep pointing
         * at them.
         */
        followKey(element, state, old, get, set) {
            const path = element?.closest?.('[data-cf-editor-item]')?.dataset
                .cfEditorItem
            const key = get('key')

            if (
                key &&
                (savedKeys.get(path) === key || key !== keyFromLabel(old))
            ) {
                return
            }

            set('key', keyFromLabel(state))
        },

        // Adding, copying, deleting and reordering, through the builder's actions.

        schemaComponent(listPath) {
            return this.$root.querySelector(
                `[data-cf-editor-items="${CSS.escape(listPath)}"]`,
            )?.dataset.cfSchemaComponent
        },

        listKeys(listPath) {
            const segments = listPath.split('.')
            const list =
                segments.length === 1
                    ? this.state[listPath]
                    : itemAt(this.state, `${segments[0]}.${segments[1]}`)?.data
                          ?.questions

            return Object.keys(list ?? {})
        },

        async callItemAction(listPath, action, args = {}) {
            const schemaComponent = this.schemaComponent(listPath)

            if (!schemaComponent || this.isBusy) {
                return null
            }

            const before = this.listKeys(listPath)

            this.isBusy = true

            try {
                await this.$wire.mountAction(action, args, { schemaComponent })
            } finally {
                this.isBusy = false
            }

            return (
                this.listKeys(listPath).find((key) => !before.includes(key)) ??
                null
            )
        },

        openPicker(listPath) {
            this.picker = this.picker === listPath ? null : listPath
            this.pickerQuery = ''

            if (this.picker) {
                this.$nextTick(() =>
                    this.$root
                        .querySelector('[data-cf-editor-picker-search]')
                        ?.focus(),
                )
            }
        },

        get pickerGroups() {
            const query = this.pickerQuery.trim().toLowerCase()
            const isGroupList = this.picker !== 'steps'
            const groups = {}
            let index = 0

            for (const [name, type] of Object.entries(configuration.types)) {
                if (isGroupList && type.group === 'layout') {
                    continue
                }

                if (query && !type.label.toLowerCase().includes(query)) {
                    continue
                }

                ;(groups[type.group] ??= []).push({
                    name,
                    ...type,
                    index: index++,
                })
            }

            return Object.entries(groups).map(([group, types]) => ({
                group,
                label: configuration.groups[group] ?? group,
                types,
            }))
        },

        async addStep(type) {
            const listPath = this.picker ?? 'steps'
            const keys = this.listKeys(listPath)

            // New steps go after the selected one (or the step around it).
            const selectedKey =
                listPath === 'steps'
                    ? stepPathOf(this.selected)?.split('.')[1]
                    : this.selected?.startsWith(`${listPath}.`)
                      ? this.selected.split('.')[3]
                      : null
            const after = keys.includes(selectedKey) ? selectedKey : keys.at(-1)

            this.picker = null

            const added = after
                ? await this.callItemAction(listPath, 'addBetween', {
                      block: type,
                      afterItem: after,
                  })
                : await this.callItemAction(listPath, 'add', { block: type })

            if (added) {
                this.select(`${listPath}.${added}`)
                this.focusInspector()
            }
        },

        async addEnding() {
            const added = await this.callItemAction('endings', 'add')

            if (added) {
                this.select(`endings.${added}`)
                this.focusInspector()
            }
        },

        listOf(path) {
            return path.split('.').slice(0, -1).join('.')
        },

        async duplicate(path) {
            const copied = await this.callItemAction(
                this.listOf(path),
                'clone',
                {
                    item: path.split('.').at(-1),
                },
            )

            if (copied) {
                this.select(`${this.listOf(path)}.${copied}`)
            }
        },

        canDelete(path) {
            const listPath = this.listOf(path)

            // Groups keep at least one question, and there's always an ending.
            return listPath === 'steps' || this.listKeys(listPath).length > 1
        },

        async remove(path) {
            if (this.confirmingDelete !== path) {
                this.confirmingDelete = path

                return
            }

            const listPath = this.listOf(path)
            const keys = this.listKeys(listPath)
            const index = keys.indexOf(path.split('.').at(-1))
            const neighbour = keys[index + 1] ?? keys[index - 1]

            this.confirmingDelete = null

            if (
                this.selected === path ||
                this.selected?.startsWith(`${path}.`)
            ) {
                this.select(
                    neighbour
                        ? `${listPath}.${neighbour}`
                        : (stepPathOf(listPath) ?? this.firstPath()),
                )
            }

            await this.callItemAction(listPath, 'delete', {
                item: path.split('.').at(-1),
            })
        },

        async reorder(listPath, keys) {
            if (keys.join() === this.listKeys(listPath).join()) {
                return
            }

            await this.callItemAction(listPath, 'reorder', { items: keys })
        },

        moveSelected(direction) {
            const path = this.selected

            if (!path || FIXED_PATHS.includes(path)) {
                return
            }

            const listPath = this.listOf(path)
            const keys = this.listKeys(listPath)
            const key = path.split('.').at(-1)
            const index = keys.indexOf(key)
            const target = index + direction

            if (index === -1 || target < 0 || target >= keys.length) {
                return
            }

            const reordered = [...keys]

            reordered.splice(index, 1)
            reordered.splice(target, 0, key)

            this.reorder(listPath, reordered).then(() =>
                this.$nextTick(() =>
                    this.$root
                        .querySelector(
                            `[data-cf-editor-row="${CSS.escape(path)}"]`,
                        )
                        ?.focus(),
                ),
            )
        },

        startDrag(event, path) {
            this.dragging = { path, list: this.listOf(path) }
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/plain', path)
        },

        dragOver(event, path) {
            // Items only move within their own list.
            if (!this.dragging || this.listOf(path) !== this.dragging.list) {
                return
            }

            event.preventDefault()
            this.dropTarget = path
        },

        drop(event, path) {
            if (!this.dragging || this.listOf(path) !== this.dragging.list) {
                return
            }

            event.preventDefault()

            const { list } = this.dragging
            const moved = this.dragging.path.split('.').at(-1)
            const keys = this.listKeys(list)
            const target = path.split('.').at(-1)
            const isBelow = keys.indexOf(moved) < keys.indexOf(target)

            // Dropping on a row puts the item in that row's place.
            const before = isBelow
                ? (keys[keys.indexOf(target) + 1] ?? null)
                : target

            this.endDrag()
            this.reorder(list, moveBefore(keys, moved, before))
        },

        endDrag() {
            this.dragging = null
            this.dropTarget = null
        },

        focusInspector() {
            this.$nextTick(() =>
                setTimeout(() => {
                    const field = this.$root.querySelector(
                        `[data-cf-editor-item="${CSS.escape(this.selected)}"] input:not([type=hidden]), [data-cf-editor-item="${CSS.escape(this.selected)}"] textarea`,
                    )

                    field?.focus()
                }, 50),
            )
        },

        // The preview.

        get preview() {
            const path = this.exists(this.previewPath)
                ? this.previewPath
                : this.firstPreviewPath

            if (path === 'welcome') {
                return { kind: 'welcome', path, item: this.state.welcome ?? {} }
            }

            if (path?.startsWith('endings.')) {
                return { kind: 'ending', path, item: itemAt(this.state, path) }
            }

            const item = itemAt(this.state, path)

            return item
                ? { kind: 'step', path, item, number: this.numberOf(path) }
                : { kind: 'empty', path: null }
        },

        get firstPreviewPath() {
            return previewSequence(this.state)[0] ?? null
        },

        numberOf(path) {
            return (
                this.outlineItems.find((item) => item.path === path)?.number ??
                null
            )
        },

        /**
         * The questions on the previewed screen: one, or a group's questions.
         */
        get previewQuestions() {
            const { kind, item, path } = this.preview

            if (kind !== 'step' || item.type === 'statement') {
                return []
            }

            if (item.type !== 'group') {
                return [{ path, ...item }]
            }

            return Object.entries(item.data?.questions ?? {}).map(
                ([uuid, question]) => ({
                    path: `${path}.questions.${uuid}`,
                    ...question,
                }),
            )
        },

        hasPreview(type) {
            return Boolean(configuration.types[type]?.hasPreview)
        },

        get progress() {
            const steps = this.outlineItems.filter(
                (item) => item.kind === 'step',
            )
            const index = steps.findIndex(
                (item) => item.path === this.preview.path,
            )

            if (this.preview.kind === 'ending') {
                return 1
            }

            return steps.length && index > -1 ? index / steps.length : 0
        },

        isPreviewSelected(path) {
            return this.selected === path
        },

        pipe(text) {
            return splitPiping(text)
        },

        tokenLabel(token) {
            return token.startsWith('@')
                ? translations.variable_token.replace(':name', token.slice(1))
                : translations.token.replace(':key', token)
        },

        isRequired(question) {
            return Boolean(question.data?.required)
        },

        image(value) {
            const image =
                value && typeof value === 'object'
                    ? Object.values(value)[0]
                    : value

            if (!image) {
                return null
            }

            return (
                this.$wire.imageUrls?.[image] ??
                (/^(https?:|data:|\/)/i.test(String(image)) ? image : null)
            )
        },

        // Text edited in place in the preview, written straight into Livewire's
        // state like the settings' own fields (which stay, hidden, so they
        // still validate and keys still follow new labels).

        textAt(path, field) {
            return String(field)
                .split('.')
                .reduce(
                    (value, key) => (value == null ? undefined : value[key]),
                    fieldsOf(this.state, path),
                )
        },

        setText(path, field, value) {
            const keys = String(field).split('.')
            const last = keys.pop()
            const target = keys.reduce(
                (value, key) => (value == null ? undefined : value[key]),
                fieldsOf(this.state, path),
            )

            if (target == null) {
                return
            }

            target[last] = value
            this.errorFields = this.errorFields.filter(
                (key) => key !== statePathOf(path, field),
            )
        },

        hasFieldError(path, field) {
            return this.errorFields.includes(statePathOf(path, field))
        },

        startEditing(path) {
            if (
                path !== this.selected &&
                !this.selected?.startsWith(`${path}.`)
            ) {
                this.select(path)
            }
        },

        fitText(element) {
            element.style.height = 'auto'
            element.style.height = `${element.scrollHeight}px`
        },

        // Answers in the preview are kept here only, to show how choices look.

        answerOf(question) {
            return this.answers[question.path]
        },

        isChosen(question, value) {
            const answer = this.answerOf(question)

            return Array.isArray(answer)
                ? answer.includes(value)
                : answer === value
        },

        choose(question, value, isMultiple = false) {
            const answer = this.answerOf(question)

            if (!isMultiple) {
                this.answers[question.path] = answer === value ? null : value

                return
            }

            const list = Array.isArray(answer) ? answer : []

            this.answers[question.path] = list.includes(value)
                ? list.filter((item) => item !== value)
                : [...list, value]
        },

        /**
         * A question's options, with letters. `withEmpty` keeps options without
         * a label yet, so they can be named in the preview.
         */
        options(question, name = 'options', { withEmpty = false } = {}) {
            return Object.entries(question.data?.settings?.[name] ?? {})
                .map(([uuid, option]) => ({ uuid, ...option }))
                .filter(
                    (option) =>
                        withEmpty || String(option?.label ?? '').trim() !== '',
                )
                .map((option, index) => ({
                    uuid: option.uuid,
                    value:
                        option.value ||
                        String(option.label ?? '')
                            .trim()
                            .toLowerCase()
                            .replace(/[^a-z0-9]+/g, '_'),
                    label: String(option.label ?? '').trim(),
                    image: option.image ?? null,
                    key: LETTERS[index] ?? null,
                }))
        },

        selectionHint(question) {
            const settings = question.data?.settings ?? {}

            if (!settings.multiple) {
                return null
            }

            const [min, max] = [
                settings.min_selections,
                settings.max_selections,
            ]

            if (min && max) {
                return translations.choose_between
                    .replace(':min', min)
                    .replace(':max', max)
            }

            if (min) {
                return translations.choose_at_least.replace(':min', min)
            }

            if (max) {
                return translations.choose_up_to.replace(':max', max)
            }

            return translations.choose_as_many
        },

        scale(question) {
            const settings = question.data?.settings ?? {}
            const isNps = question.type === 'nps'
            const start = isNps ? 0 : parseInt(settings.start, 10) || 0
            const end = isNps
                ? 10
                : Math.max(start + 1, parseInt(settings.end, 10) || 10)

            return Array.from(
                { length: end - start + 1 },
                (_, index) => start + index,
            )
        },

        scaleLabels(question) {
            const settings = question.data?.settings ?? {}
            const isNps = question.type === 'nps'

            return {
                start:
                    settings.start_label ||
                    (isNps ? translations.nps_start : ''),
                end: settings.end_label || (isNps ? translations.nps_end : ''),
            }
        },

        ratingSteps(question) {
            const steps = Math.min(
                10,
                Math.max(2, parseInt(question.data?.settings?.steps, 10) || 5),
            )

            return Array.from({ length: steps }, (_, index) => index + 1)
        },

        ratingShape(question) {
            return question.data?.settings?.shape || 'star'
        },

        yesNo(question) {
            const settings = question.data?.settings ?? {}
            const isConsent = question.type === 'legal_consent'

            return [
                {
                    value: true,
                    key: 'Y',
                    label:
                        settings.yes_label ||
                        (isConsent ? translations.accept : translations.yes),
                },
                {
                    value: false,
                    key: 'N',
                    label:
                        settings.no_label ||
                        (isConsent ? translations.decline : translations.no),
                },
            ]
        },

        parts(question) {
            const available = configuration.composites[question.type] ?? []
            const chosen = question.data?.settings?.parts ?? []
            const required = question.data?.settings?.required_parts ?? []
            const isRequired = Boolean(question.data?.required)

            return available
                .filter((part) => !chosen.length || chosen.includes(part.key))
                .map((part) => ({
                    ...part,
                    isRequired:
                        isRequired &&
                        (required.length
                            ? required.includes(part.key)
                            : part.isRequiredByDefault),
                }))
        },

        segments(question) {
            if (question.type === 'time') {
                const isTwelveHour =
                    String(question.data?.settings?.twelve_hour ?? '') === '1'

                return [
                    {
                        key: 'hour',
                        label: translations.hour,
                        placeholder: 'HH',
                        length: 2,
                    },
                    {
                        key: 'minute',
                        label: translations.minute,
                        placeholder: 'MM',
                        length: 2,
                        separator: ':',
                    },
                    ...(isTwelveHour
                        ? [
                              {
                                  key: 'meridiem',
                                  label: translations.meridiem,
                                  placeholder: 'AM',
                                  length: 2,
                              },
                          ]
                        : []),
                ]
            }

            const boxes = {
                D: {
                    key: 'day',
                    label: translations.day,
                    placeholder: 'DD',
                    length: 2,
                },
                M: {
                    key: 'month',
                    label: translations.month,
                    placeholder: 'MM',
                    length: 2,
                },
                Y: {
                    key: 'year',
                    label: translations.year,
                    placeholder: 'YYYY',
                    length: 4,
                },
            }

            return (question.data?.settings?.format || 'DMY')
                .split('')
                .map((letter, index) => ({
                    ...boxes[letter],
                    separator: index ? '/' : null,
                }))
        },

        hasCalendar(question) {
            return (
                question.type === 'date' &&
                question.data?.settings?.calendar !== false
            )
        },

        placeholder(question) {
            const placeholder = question.data?.settings?.placeholder

            if (placeholder) {
                return placeholder
            }

            return (
                {
                    email: translations.email_placeholder,
                    phone: translations.phone_placeholder,
                    number: translations.number_placeholder,
                    website: 'https://',
                }[question.type] ?? translations.placeholder
            )
        },

        inputType(question) {
            return (
                {
                    email: 'email',
                    website: 'url',
                    phone: 'tel',
                    number: 'text',
                }[question.type] ?? 'text'
            )
        },

        // The theme.

        get theme() {
            return themeFrom(this.state.theme ?? {}, configuration.theme)
        },

        get themeStyle() {
            if (!this.theme) {
                return {}
            }

            return cssVariables(this.theme, {
                ...configuration.theme,
                resolveImage: (image) => this.image(image),
            })
        },

        get themeMode() {
            if (!this.theme) {
                return null
            }

            return isDark(this.theme) ? 'dark' : 'light'
        },

        get fontUrl() {
            return this.theme
                ? fontStylesheetUrl(this.theme.font, configuration.theme)
                : null
        },

        get contrastIssues() {
            if (!this.theme) {
                return []
            }

            return contrastIssues(this.theme).map((issue) =>
                translations.contrast[issue.check]
                    .replace(':ratio', formatRatio(issue.ratio))
                    .replace(':minimum', issue.minimum),
            )
        },
    }
}
