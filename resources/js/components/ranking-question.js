import { dropIndex, move, reconcile } from '../support/ranking.js'

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

            this.commit(move(this.order, from, to))
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
                this.commit(this.orderBeforeGrab)
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

            pointer = { value, startY: event.clientY, element }
            element.setPointerCapture?.(event.pointerId)
            this.grabbed = value
        },

        drag(event) {
            if (!pointer) {
                return
            }

            pointer.element.style.translate = `0 ${event.clientY - pointer.startY}px`
        },

        endDrag(event) {
            if (!pointer) {
                return
            }

            const rows = Array.from(root.querySelectorAll('[data-cf-rank-row]'))
            const midpoints = rows.map((row) => {
                const rect = row.getBoundingClientRect()

                return rect.top + rect.height / 2
            })
            const from = this.order.indexOf(pointer.value)
            const to = dropIndex(
                midpoints.filter((_, index) => index !== from),
                event.clientY,
            )

            pointer.element.style.translate = ''
            this.commit(move(this.order, from, to))
            this.announce('dropped', pointer.value)
            this.grabbed = null
            pointer = null
        },
    }
}
