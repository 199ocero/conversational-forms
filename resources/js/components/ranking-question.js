import { dropIndex, move, reconcile, shiftFor } from '../support/ranking.js'

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches

// Keeps the row's own box-shadow transition alongside the slide.
const SLIDE =
    'translate 200ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 150ms ease'

/**
 * Order a list by dragging (mouse, pen or touch) or with the keyboard:
 * Space picks an item up, ↑ / ↓ move it, Space drops it and Escape cancels.
 * Every change is announced to screen readers.
 */
export default function rankingQuestionComponent({
    state,
    items = [],
    translations = {},
}) {
    let root = null
    let pointer = null

    const values = items.map((item) => item.value)
    const labelOf = (value) =>
        items.find((item) => item.value === value)?.label ?? value

    return {
        state,

        order: [],

        grabbed: null,

        orderBeforeGrab: null,

        announcement: '',

        init() {
            root = this.$el
            this.order = reconcile(this.state, values)
            this.state = [...this.order]

            this.$watch('state', (value) => {
                const order = reconcile(value, values)

                if (order.join('|') !== this.order.join('|')) {
                    this.order = order
                }
            })
        },

        label(value) {
            return labelOf(value)
        },

        position(value) {
            return this.order.indexOf(value) + 1
        },

        commit(order) {
            this.order = order
            this.state = [...order]
        },

        rows() {
            return Array.from(root.querySelectorAll('[data-cf-rank-row]'))
        },

        /**
         * Reorder with a slide: rows start where they are on screen (including
         * any drag offset) and glide into their new places.
         */
        reorder(order) {
            const before = new Map(
                this.rows().map((row) => [
                    row.dataset.cfRankValue,
                    row.getBoundingClientRect().top,
                ]),
            )

            for (const row of this.rows()) {
                row.style.transition = 'none'
                row.style.translate = ''
            }

            this.commit(order)

            this.$nextTick(() => {
                if (prefersReducedMotion()) {
                    return
                }

                const rows = this.rows()

                for (const row of rows) {
                    const delta =
                        (before.get(row.dataset.cfRankValue) ?? 0) -
                        row.getBoundingClientRect().top

                    row.style.translate = delta ? `0 ${delta}px` : ''
                }

                // Apply the starting offsets before animating to the new places.
                void root.offsetHeight

                requestAnimationFrame(() => {
                    for (const row of rows) {
                        row.style.transition = SLIDE
                        row.style.translate = ''
                    }
                })
            })
        },

        announce(key, value) {
            this.announcement = (translations[key] ?? '')
                .replace(':item', labelOf(value))
                .replace(':position', this.position(value))
                .replace(':total', this.order.length)
        },

        moveBy(value, offset) {
            const from = this.order.indexOf(value)
            const to = from + offset

            if (to < 0 || to >= this.order.length) {
                return
            }

            this.reorder(move(this.order, from, to))
            this.announce('moved', value)
            this.$nextTick(() => this.focusItem(value))
        },

        focusItem(value) {
            root.querySelector(
                `[data-cf-rank-item="${CSS.escape(value)}"]`,
            )?.focus()
        },

        handleKeydown(value, event) {
            if (event.altKey || event.metaKey || event.ctrlKey) {
                return
            }

            const index = this.order.indexOf(value)

            if (event.key === ' ') {
                event.preventDefault()

                if (this.grabbed === value) {
                    this.grabbed = null
                    this.announce('dropped', value)
                } else {
                    this.grabbed = value
                    this.orderBeforeGrab = [...this.order]
                    this.announce('grabbed', value)
                }

                return
            }

            if (event.key === 'Escape' && this.grabbed !== null) {
                event.preventDefault()
                this.reorder(this.orderBeforeGrab)
                this.announce('cancelled', value)
                this.grabbed = null

                return
            }

            if (event.key === 'Enter') {
                // Enter moves on, as everywhere else in a conversation.
                event.preventDefault()
                this.grabbed = null
                this.next()

                return
            }

            const offset = { ArrowUp: -1, ArrowDown: 1 }[event.key]

            if (offset === undefined) {
                return
            }

            event.preventDefault()

            if (this.grabbed === value) {
                this.moveBy(value, offset)

                return
            }

            this.focusItem(this.order[index + offset] ?? value)
        },

        startDrag(value, event) {
            if (event.button !== 0) {
                return
            }

            event.preventDefault()

            const element = event.currentTarget.closest('[data-cf-rank-row]')
            const rows = this.rows()
            const rects = rows.map((row) => row.getBoundingClientRect())
            const from = rows.indexOf(element)
            const gap = rects.length > 1 ? rects[1].top - rects[0].bottom : 0

            pointer = {
                value,
                element,
                rows,
                from,
                to: from,
                startY: event.clientY,
                step: rects[from].height + gap,
                center: rects[from].top + rects[from].height / 2,
                // Midpoints of the other rows, where the dragged row's centre changes places.
                midpoints: rects
                    .filter((_, index) => index !== from)
                    .map((rect) => rect.top + rect.height / 2),
            }

            element.setPointerCapture?.(event.pointerId)
            element.style.transition = 'none'

            for (const row of rows) {
                if (row !== element) {
                    row.style.transition = prefersReducedMotion()
                        ? 'none'
                        : SLIDE
                }
            }

            this.grabbed = value
        },

        drag(event) {
            if (!pointer) {
                return
            }

            const offset = event.clientY - pointer.startY
            const to = dropIndex(pointer.midpoints, pointer.center + offset)

            pointer.element.style.translate = `0 ${offset}px`

            if (to === pointer.to) {
                return
            }

            // The other rows make room, so the drop position is visible while dragging.
            pointer.to = to
            pointer.rows.forEach((row, index) => {
                if (row !== pointer.element) {
                    const shift = shiftFor(
                        index,
                        pointer.from,
                        to,
                        pointer.step,
                    )

                    row.style.translate = shift ? `0 ${shift}px` : ''
                }
            })
        },

        endDrag() {
            if (!pointer) {
                return
            }

            const { value, from, to } = pointer

            pointer = null
            this.grabbed = null
            this.reorder(move(this.order, from, to))
            this.announce('dropped', value)
        },
    }
}
