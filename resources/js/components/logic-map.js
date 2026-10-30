import { arcPath, assignLanes } from '../support/arcs.js'

const LANE_WIDTH = 18
const MIN_WIDTH = 28

/**
 * Draws the logic map's jumps as arcs beside the list of steps. Positions
 * are measured from the rendered cards, so they can be any height, and are
 * measured again when the page resizes.
 */
export default function logicMapComponent({ edges = [] }) {
    let root = null
    let observer = null

    return {
        arcs: [],

        width: 0,

        height: 0,

        active: null,

        init() {
            root = this.$el
            observer = new ResizeObserver(() => this.draw())
            observer.observe(root)
            this.$nextTick(() => this.draw())
        },

        destroy() {
            observer?.disconnect()
        },

        draw() {
            const box = root.getBoundingClientRect()
            const center = (id) => {
                const node = root.querySelector(
                    `[data-cf-node="${CSS.escape(id)}"]`,
                )

                if (!node) {
                    return null
                }

                const rect = node.getBoundingClientRect()

                return {
                    y: rect.top - box.top + rect.height / 2,
                    right: rect.right - box.left,
                }
            }

            const drawn = edges
                .map((edge, index) => ({
                    edge,
                    index,
                    from: center(edge.from),
                    to: center(edge.to),
                }))
                .filter(
                    ({ edge, from, to }) => edge.kind !== 'next' && from && to,
                )

            const lanes = assignLanes(
                drawn.map(({ from, to }) => ({ from: from.y, to: to.y })),
            )
            const x = Math.max(0, ...drawn.map(({ from }) => from.right)) + 8

            this.arcs = drawn.map(({ edge, index, from, to }, position) => {
                const width = MIN_WIDTH + lanes[position] * LANE_WIDTH

                return {
                    index,
                    kind: edge.kind,
                    label: edge.label,
                    from: edge.from,
                    to: edge.to,
                    d: arcPath(x, from.y, to.y, width),
                }
            })

            this.width = box.width
            this.height = box.height
        },

        /**
         * The arcs as SVG, with the hovered rule's arc highlighted.
         */
        markup() {
            return this.arcs
                .map((arc) => {
                    const isActive =
                        this.active !== null &&
                        this.active.from === arc.from &&
                        this.active.to === arc.to
                    const classes = [
                        'cf-logic-arc',
                        arc.kind === 'end' ? 'cf-logic-arc-end' : '',
                        isActive ? 'cf-active' : '',
                    ].join(' ')

                    return `<path d="${arc.d}" class="${classes}" marker-end="url(#cf-logic-arrow)"></path>`
                })
                .join('')
        },

        isActive(id) {
            return (
                this.active !== null &&
                [this.active.from, this.active.to].includes(id)
            )
        },
    }
}
