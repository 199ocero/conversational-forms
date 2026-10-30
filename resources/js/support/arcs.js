/**
 * Pure helpers for the logic map's arcs, shared by the Alpine component and
 * the tests. Jumps only go forward, so every arc spans a range of rows.
 */

/**
 * Give each arc a lane (0, 1, 2, …) so arcs whose rows overlap sit side by
 * side instead of on top of each other. Shorter arcs get the inner lanes.
 *
 * @param {{from: number, to: number}[]} spans Top and bottom of each arc.
 * @returns {number[]} The lane of each arc, in the given order.
 */
export function assignLanes(spans) {
    const order = spans
        .map((span, index) => ({
            index,
            top: Math.min(span.from, span.to),
            bottom: Math.max(span.from, span.to),
        }))
        .sort((a, b) => a.bottom - a.top - (b.bottom - b.top) || a.top - b.top)

    const lanes = []
    const taken = []

    for (const arc of order) {
        let lane = 0

        while (
            (taken[lane] ?? []).some(
                (other) => arc.top < other.bottom && other.top < arc.bottom,
            )
        ) {
            lane++
        }

        taken[lane] = [...(taken[lane] ?? []), arc]
        lanes[arc.index] = lane
    }

    return lanes
}

/**
 * An SVG path that leaves `x` at `fromY`, bows out to the right by `width`
 * and comes back to `x` at `toY`.
 */
export function arcPath(x, fromY, toY, width) {
    return `M ${x} ${fromY} C ${x + width} ${fromY}, ${x + width} ${toY}, ${x} ${toY}`
}
