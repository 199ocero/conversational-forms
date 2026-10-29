import { compose, decompose, isValid } from '../support/segments.js'

/**
 * Date (DD / MM / YYYY in any order) and time (HH : MM) answers typed into
 * separate boxes. Focus moves on as each box fills up, and Backspace in an
 * empty box moves back.
 */
export default function segmentsQuestionComponent({
    state,
    kind = 'date',
    segments = [],
    shouldAutoAdvance = false,
}) {
    let root = null
    let lastComposed = null

    return {
        state,

        values: {},

        init() {
            root = this.$el
            this.values = decompose(kind, this.state)

            // Only external changes (for example a value set on the server)
            // update the boxes; the value composed while typing never does.
            this.$watch('state', (value) => {
                if (value === lastComposed) {
                    return
                }

                const values = decompose(kind, value)

                if (Object.keys(values).length) {
                    this.values = values
                }
            })
        },

        inputs() {
            return Array.from(root.querySelectorAll('[data-cf-segment]'))
        },

        focusSegment(index) {
            const input = this.inputs()[index]

            input?.focus()
            input?.select()
        },

        handleInput(index, event) {
            const segment = segments[index]
            const digits = event.target.value
                .replace(/\D/g, '')
                .slice(0, segment.length)

            event.target.value = digits
            this.values[segment.key] = digits
            this.update()

            if (
                digits.length === segment.length &&
                index < segments.length - 1
            ) {
                this.focusSegment(index + 1)
            }
        },

        handleKeydown(index, event) {
            const input = event.target
            const atStart =
                input.selectionStart === 0 && input.selectionEnd === 0
            const atEnd = input.selectionStart === input.value.length

            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                event.preventDefault()
                this.focusSegment(index - 1)

                return
            }

            if (event.key === 'ArrowLeft' && atStart && index > 0) {
                event.preventDefault()
                this.focusSegment(index - 1)

                return
            }

            if (
                ['ArrowRight', '/', '-', '.', ':'].includes(event.key) &&
                (atEnd || event.key !== 'ArrowRight') &&
                index < segments.length - 1
            ) {
                event.preventDefault()
                this.focusSegment(index + 1)
            }
        },

        update() {
            const value = compose(kind, this.values, segments)

            lastComposed = value
            this.state = value

            if (shouldAutoAdvance && value !== null && isValid(kind, value)) {
                root.dispatchEvent(
                    new CustomEvent('cf-answered', { bubbles: true }),
                )
            }
        },

        openCalendar() {
            const picker = root.querySelector('[data-cf-calendar]')

            if (!picker) {
                return
            }

            picker.value = isValid(kind, this.state) ? this.state : ''

            try {
                picker.showPicker()
            } catch {
                picker.focus()
            }
        },

        chooseFromCalendar(event) {
            if (!event.target.value) {
                return
            }

            this.values = decompose(kind, event.target.value)
            this.update()
        },
    }
}
