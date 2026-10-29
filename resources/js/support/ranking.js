/**
 * Move the item at `from` to `to`, returning a new array.
 */
export function move(items, from, to) {
    const result = [...items]

    if (
        from === to ||
        from < 0 ||
        to < 0 ||
        from >= items.length ||
        to >= items.length
    ) {
        return result
    }

    const [item] = result.splice(from, 1)
    result.splice(to, 0, item)

    return result
}

/**
 * Keep a saved order that still matches the options; otherwise fall back to
 * the options' order. New options are added at the end.
 */
export function reconcile(order, values) {
    if (!Array.isArray(order)) {
        return [...values]
    }

    const known = order.map(String).filter((value) => values.includes(value))

    return [...known, ...values.filter((value) => !known.includes(value))]
}

/**
 * Where an item dragged to vertical position `y` should be inserted, given
 * the vertical midpoints of the other items (without the dragged one).
 */
export function dropIndex(midpoints, y) {
    return midpoints.filter((midpoint) => y > midpoint).length
}
