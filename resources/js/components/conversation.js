import {
    getNavigationDirection,
    isAdvanceKey,
    shouldHandleKey,
} from '../keyboard/guard.js'

/**
 * The conversation stage. Alpine owns which step is visible; PHP owns
 * validation and the path through the conversation (see `Conversation::advance()`).
 */
export default function conversationComponent({ startStep = null }) {
    return {
        current: null,

        direction: 'forward',

        history: [],

        path: [],

        progress: 0,

        isAdvancing: false,

        announcement: '',

        init() {
            this.path = this.readPath()
            this.current = this.path.includes(startStep)
                ? startStep
                : (this.path[0] ?? null)

            this.$nextTick(() => this.afterStepShown({ shouldFocus: false }))
        },

        readPath() {
            return JSON.parse(this.$refs.path?.value ?? '[]')
        },

        isActive(step) {
            return this.current === step
        },

        async next() {
            if (this.isAdvancing || this.current === null) {
                return
            }

            this.isAdvancing = true

            try {
                const response = await this.$advance({ fromStep: this.current })

                if (!response) {
                    this.focusFirstInvalidControl()

                    return
                }

                this.path = response.path
                this.progress = response.progress

                if (response.next) {
                    this.history.push(this.current)
                    this.goTo(response.next, 'forward')

                    return
                }

                this.$dispatch('cf-conversation-finished', {
                    ending: response.ending,
                })
            } finally {
                this.isAdvancing = false
            }
        },

        previous() {
            const step = this.history.pop()

            if (step === undefined) {
                return
            }

            this.goTo(step, 'backward')
        },

        goTo(step, direction) {
            this.direction = direction
            this.current = step

            this.$nextTick(() => this.afterStepShown({ shouldFocus: true }))
        },

        afterStepShown({ shouldFocus }) {
            const element = this.getStepElement(this.current)

            if (!element) {
                return
            }

            const position = this.path.indexOf(this.current) + 1

            this.announcement = `${position} / ${this.path.length}`

            if (shouldFocus) {
                this.focusStep(element)
            }

            window.dispatchEvent(
                new CustomEvent('cf-step-shown', {
                    detail: { step: this.current, element },
                }),
            )

            // Libraries that measured a hidden container (canvas, editors, maps)
            // re-measure on resize once the step is visible.
            element.addEventListener(
                'transitionend',
                () => window.dispatchEvent(new Event('resize')),
                { once: true },
            )
        },

        focusStep(element) {
            const control =
                element.querySelector('[autofocus]') ??
                element.querySelector(
                    'input:not([type=hidden]), textarea, select, [tabindex="0"], button',
                )

            ;(control ?? element).focus({ preventScroll: true })
        },

        focusFirstInvalidControl() {
            this.$nextTick(() =>
                this.getStepElement(this.current)
                    ?.querySelector('[aria-invalid="true"]')
                    ?.focus(),
            )
        },

        getStepElement(step) {
            return this.$root.querySelector(
                `[data-cf-step="${CSS.escape(step)}"]`,
            )
        },

        handleKeydown(event) {
            if (!shouldHandleKey(event)) {
                return
            }

            const direction = getNavigationDirection(event)

            if (direction) {
                event.preventDefault()
                direction === 'next' ? this.next() : this.previous()

                return
            }

            if (isAdvanceKey(event)) {
                event.preventDefault()
                this.next()
            }
        },
    }
}
