import { shouldHandleKey } from '../keyboard/guard.js'
import {
    getChoiceForDigits,
    isSameValue,
    isTextEntry,
} from '../support/choices.js'

const DIGIT_BUFFER_TIMEOUT = 600

/**
 * Shared by every question that renders selectable answers (multiple choice,
 * yes / no, opinion scale): letter and digit shortcuts, roving focus with arrow
 * keys, "Other" answers, and the `cf-answered` event that drives auto-advance.
 */
export default function choiceQuestionComponent({
    state,
    choices = [],
    isMultiple = false,
    maxSelections = null,
    otherKey = null,
    shouldAutoAdvance = false,
}) {
    let root = null
    let digits = ''
    let digitsTimeout = null

    const values = choices.map((choice) => choice.value)
    const isKnownValue = (value) =>
        values.some((known) => isSameValue(known, value))

    return {
        state,

        otherText: '',

        isOtherSelected: false,

        init() {
            root = this.$el

            if (isMultiple) {
                if (!Array.isArray(this.state)) {
                    this.state = []
                }

                const other = this.state.find((value) => !isKnownValue(value))

                if (otherKey && other !== undefined) {
                    this.isOtherSelected = true
                    this.otherText = String(other)
                }
            } else if (
                otherKey &&
                ![null, undefined, ''].includes(this.state) &&
                !isKnownValue(this.state)
            ) {
                this.isOtherSelected = true
                this.otherText = String(this.state)
            }

            this.$watch('otherText', () => this.syncOther())
        },

        isSelected(value) {
            if (isMultiple) {
                return (this.state ?? []).some((selected) =>
                    isSameValue(selected, value),
                )
            }

            return !this.isOtherSelected && isSameValue(this.state, value)
        },

        choose(value, { isExplicit = false } = {}) {
            if (isMultiple) {
                const selected = [...(this.state ?? [])]
                const index = selected.findIndex((item) =>
                    isSameValue(item, value),
                )

                if (index !== -1) {
                    selected.splice(index, 1)
                } else if (
                    maxSelections === null ||
                    selected.length < maxSelections
                ) {
                    selected.push(value)
                }

                this.state = selected

                return
            }

            this.isOtherSelected = false
            this.state = value

            if (isExplicit) {
                this.answered()
            }
        },

        chooseOther() {
            this.isOtherSelected = isMultiple ? !this.isOtherSelected : true
            this.syncOther()

            if (this.isOtherSelected) {
                this.$nextTick(() =>
                    root.querySelector('.cf-choice-other-input')?.focus(),
                )
            }
        },

        syncOther() {
            if (isMultiple) {
                const known = (this.state ?? []).filter(isKnownValue)
                const text = this.otherText.trim()

                this.state =
                    this.isOtherSelected && text !== ''
                        ? [...known, text]
                        : known

                return
            }

            if (this.isOtherSelected) {
                this.state =
                    this.otherText.trim() === '' ? null : this.otherText
            }
        },

        answered() {
            if (!shouldAutoAdvance) {
                return
            }

            root.dispatchEvent(
                new CustomEvent('cf-answered', { bubbles: true }),
            )
        },

        getTabIndex(value, index, isOther = false) {
            const hasSelection = isMultiple
                ? (this.state ?? []).length > 0 || this.isOtherSelected
                : this.isOtherSelected || isKnownValue(this.state)

            if (!hasSelection) {
                return index === 0 ? 0 : -1
            }

            if (isOther) {
                return this.isOtherSelected && !isMultiple ? 0 : -1
            }

            if (isMultiple) {
                return index === 0 ? 0 : -1
            }

            return this.isSelected(value) ? 0 : -1
        },

        isInActiveStep() {
            const step = root.closest('[data-cf-step]')
            const conversation = root.closest('.cf-conversation')

            if (!step?.classList.contains('cf-active')) {
                return false
            }

            if (step.hasAttribute('data-cf-several-questions')) {
                return root.contains(document.activeElement)
            }

            return (
                conversation?.dataset.cfFullScreen === 'true' ||
                conversation?.contains(document.activeElement)
            )
        },

        handleShortcut(event) {
            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                !shouldHandleKey(event) ||
                isTextEntry(event.target) ||
                !this.isInActiveStep()
            ) {
                return
            }

            const key = event.key.toLowerCase()

            if (/^[0-9]$/.test(key)) {
                this.handleDigit(key, event)

                return
            }

            if (otherKey && key === otherKey) {
                event.preventDefault()
                this.chooseOther()

                return
            }

            const choice = choices.find((choice) => choice.key === key)

            if (!choice) {
                return
            }

            event.preventDefault()
            this.choose(choice.value, { isExplicit: true })

            if (root.contains(document.activeElement)) {
                this.focusChoice(choice.value)
            }
        },

        /**
         * Digits are buffered so that "1" then "0" selects 10 on a 0–10 scale.
         * When a longer answer could still be typed, auto-advance waits.
         */
        handleDigit(digit, event) {
            clearTimeout(digitsTimeout)

            const { choice, buffer, isAmbiguous } = getChoiceForDigits(
                choices,
                digits,
                digit,
            )

            digits = buffer

            if (!choice) {
                return
            }

            event.preventDefault()
            this.choose(choice.value)

            if (!isAmbiguous) {
                digits = ''
                this.answered()

                return
            }

            digitsTimeout = setTimeout(() => {
                digits = ''
                this.answered()
            }, DIGIT_BUFFER_TIMEOUT)
        },

        buttons() {
            return Array.from(root.querySelectorAll('[data-cf-choice]'))
        },

        hasSelection() {
            return isMultiple
                ? (this.state ?? []).length > 0
                : this.isOtherSelected || isKnownValue(this.state)
        },

        /**
         * Move focus to the answer a shortcut chose, so selection and focus
         * stay together (as in a native radio group).
         */
        focusChoice(value) {
            const index = choices.findIndex((choice) =>
                isSameValue(choice.value, value),
            )

            this.$nextTick(() => this.buttons()[index]?.focus())
        },

        handleChoiceKeydown(event) {
            if (event.altKey || event.metaKey || event.ctrlKey) {
                return
            }

            // Enter moves on, as everywhere else in a conversation. With a single
            // answer on its own screen, the button's native Enter-to-click already
            // does that (choose, then auto-advance). Elsewhere (multiple answers,
            // or several questions on one screen) Enter must not just toggle the
            // focused answer: it chooses a single answer if needed, then moves on.
            const button = event.target.closest?.('[data-cf-choice]')
            const isOnSharedScreen = root
                .closest('[data-cf-step]')
                ?.hasAttribute('data-cf-several-questions')

            if (
                event.key === 'Enter' &&
                !event.shiftKey &&
                button &&
                (isMultiple || isOnSharedScreen)
            ) {
                event.preventDefault()

                // Keep an existing answer; only a screen with nothing chosen yet
                // takes the focused answer.
                if (!isMultiple && !this.hasSelection()) {
                    const index = this.buttons().indexOf(button)

                    if (index !== -1 && index < choices.length) {
                        this.choose(choices[index].value)
                    }
                }

                this.next()

                return
            }

            const offset = {
                ArrowDown: 1,
                ArrowRight: 1,
                ArrowUp: -1,
                ArrowLeft: -1,
            }[event.key]

            if (offset === undefined) {
                return
            }

            const buttons = Array.from(
                root.querySelectorAll('[data-cf-choice]'),
            )
            const index = buttons.indexOf(document.activeElement)

            if (index === -1) {
                return
            }

            event.preventDefault()

            const nextIndex = (index + offset + buttons.length) % buttons.length

            buttons[nextIndex].focus()

            // Radio groups select the focused answer, but never auto-advance on arrows.
            if (!isMultiple && nextIndex < choices.length) {
                this.choose(choices[nextIndex].value)
            }
        },
    }
}
