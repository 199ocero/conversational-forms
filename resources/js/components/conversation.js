import {
    getNavigationDirection,
    isAdvanceKey,
    shouldHandleKey,
} from '../keyboard/guard.js'

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches

/**
 * The conversation stage. Alpine owns which step is visible; PHP owns
 * validation and the path through the conversation (`Conversation::advance()`).
 *
 * Methods are called from nested scopes (steps, questions), so they never rely
 * on `$root` or `$refs`: the conversation element is captured once in `init()`.
 */
export default function conversationComponent({
    autoAdvanceDelay = 400,
    hasWelcomeScreen = false,
    isAutoAdvanceEnabled = true,
    isCompleted = false,
    key,
    path = [],
    submitMethod = 'submitConversation',
    translations = {},
}) {
    let conversation = null
    let autoAdvanceTimeout = null

    return {
        conversationKey: key,

        current: null,

        history: [],

        path,

        progress: isCompleted ? 1 : 0,

        phase: isCompleted ? 'ended' : hasWelcomeScreen ? 'welcome' : 'steps',

        isAdvancing: false,

        announcement: '',

        init() {
            conversation = this.$el
            this.current = this.path[0] ?? null

            if (this.phase === 'steps') {
                this.$nextTick(() =>
                    this.afterStepShown({ shouldFocus: false }),
                )
            }
        },

        isInSteps() {
            return this.phase === 'steps' || this.phase === 'submitting'
        },

        isActive(step) {
            return this.isInSteps() && this.current === step
        },

        isBefore(step) {
            const index = this.path.indexOf(step)

            return index !== -1 && index < this.path.indexOf(this.current)
        },

        isLastStep(step) {
            return this.path.at(-1) === step
        },

        start() {
            if (this.phase !== 'welcome') {
                return
            }

            this.phase = 'steps'
            this.$nextTick(() => this.afterStepShown({ shouldFocus: true }))
        },

        async next() {
            if (this.phase === 'welcome') {
                this.start()

                return
            }

            if (
                this.phase !== 'steps' ||
                this.isAdvancing ||
                this.current === null
            ) {
                return
            }

            this.cancelAutoAdvance()
            this.isAdvancing = true

            let response = null

            try {
                response = await this.$advance({ fromStep: this.current })
            } finally {
                this.isAdvancing = false
            }

            if (!response) {
                this.focusFirstInvalidControl()

                return
            }

            this.path = response.path
            this.progress = response.progress

            if (response.next) {
                this.history.push(this.current)
                this.goTo(response.next)

                return
            }

            await this.submit()
        },

        async submit() {
            this.phase = 'submitting'
            this.isAdvancing = true

            try {
                // The server dispatches `cf-conversation-completed` or
                // `cf-conversation-invalid` before this promise resolves.
                await this.$wire.$call(submitMethod)
            } finally {
                this.isAdvancing = false

                if (this.phase === 'submitting') {
                    this.phase = 'steps'
                }
            }
        },

        previous() {
            if (this.phase !== 'steps' || this.isAdvancing) {
                return
            }

            const step = this.history.pop()

            if (step === undefined) {
                return
            }

            this.cancelAutoAdvance()
            this.goTo(step)
        },

        goTo(step) {
            this.current = step

            this.$nextTick(() => this.afterStepShown({ shouldFocus: true }))
        },

        afterStepShown({ shouldFocus }) {
            const element = this.getStepElement(this.current)

            if (!element) {
                return
            }

            this.announcement = (translations.progress ?? ':current / :total')
                .replace(':current', this.path.indexOf(this.current) + 1)
                .replace(':total', this.path.length)

            if (shouldFocus) {
                // Wait for the step's `inert` attribute and styles to update.
                requestAnimationFrame(() => this.focusStep(element))
            }

            window.dispatchEvent(
                new CustomEvent('cf-step-shown', {
                    detail: {
                        conversation: this.conversationKey,
                        step: this.current,
                        element,
                    },
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

        handleAnswered(event) {
            if (!isAutoAdvanceEnabled || this.phase !== 'steps') {
                return
            }

            const step = event.target.closest('[data-cf-step]')?.dataset.cfStep

            if (step !== this.current) {
                return
            }

            this.cancelAutoAdvance()

            autoAdvanceTimeout = setTimeout(
                () => this.next(),
                prefersReducedMotion() ? 0 : autoAdvanceDelay,
            )
        },

        cancelAutoAdvance() {
            clearTimeout(autoAdvanceTimeout)
        },

        handleCompleted({ key }) {
            if (key !== this.conversationKey) {
                return
            }

            this.phase = 'ended'
            this.progress = 1
            this.announcement = translations.completed ?? ''

            this.$nextTick(() =>
                requestAnimationFrame(() =>
                    conversation
                        .querySelector('[data-cf-screen="end"]')
                        ?.focus(),
                ),
            )
        },

        handleInvalid({ key, step }) {
            if (key !== this.conversationKey) {
                return
            }

            this.phase = 'steps'

            if (step && step !== this.current && this.path.includes(step)) {
                this.history = this.path.slice(0, this.path.indexOf(step))
                this.goTo(step)
            }

            this.focusFirstInvalidControl()
        },

        focusStep(element) {
            const control =
                element.querySelector('[autofocus]') ??
                element.querySelector('[data-cf-choice][tabindex="0"]') ??
                element.querySelector(
                    'input:not([type=hidden]), textarea, select, [contenteditable="true"], [tabindex="0"], button',
                )

            ;(control ?? element).focus({ preventScroll: true })
        },

        focusFirstInvalidControl() {
            // Wait for Livewire to morph the step's error messages in.
            setTimeout(() => {
                const step = this.getStepElement(this.current)

                ;(
                    step?.querySelector('[aria-invalid="true"]') ??
                    step?.querySelector('.cf-question-error:not([hidden])')
                )?.focus?.()

                step?.classList.add('cf-shake')
                setTimeout(() => step?.classList.remove('cf-shake'), 400)
            }, 50)
        },

        getStepElement(step) {
            return conversation?.querySelector(
                `[data-cf-step="${CSS.escape(step ?? '')}"]`,
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
