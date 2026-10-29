/**
 * Decide what an edit may insert so that the whole value keeps matching
 * `pattern`. Typed characters are all-or-nothing; pasted or dropped text keeps
 * the characters that fit ("9 people" pasted into a number becomes "9").
 *
 * @returns {string|null} The text to insert, or `null` to insert nothing.
 */
export function filterInsertion({
    value,
    selectionStart,
    selectionEnd,
    text,
    pattern,
    isPaste = false,
}) {
    const regex = new RegExp(pattern)
    const before = value.slice(0, selectionStart)
    const after = value.slice(selectionEnd)

    if (regex.test(before + text + after)) {
        return text
    }

    if (!isPaste) {
        return null
    }

    let kept = ''

    for (const character of text) {
        if (regex.test(before + kept + character + after)) {
            kept += character
        }
    }

    return kept === '' ? null : kept
}
