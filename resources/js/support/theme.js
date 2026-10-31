/**
 * Themes in the browser, for the builder's live preview. Mirrors
 * `Themes\Contrast`, `Themes\Theme`, `Themes\Fonts` and the Design tab's
 * `ThemeSchema::themeFrom()`; `tests/Fixtures/themes/cases.json` keeps the two
 * in step.
 */

export const TEXT = 4.5

export const NON_TEXT = 3.0

/**
 * Round like PHP's `round()`, which ignores binary representation errors.
 */
function round(value, precision) {
    const factor = 10 ** precision

    return Math.round((value + Number.EPSILON) * factor) / factor
}

/**
 * `#abc` or `#aabbcc` as `#aabbcc`, or `null` when it isn't a hex colour.
 */
export function normalize(hex) {
    let value = String(hex ?? '')
        .trim()
        .replace(/^#+/, '')
        .toLowerCase()

    if (/^[0-9a-f]{3}$/.test(value)) {
        value = value
            .split('')
            .map((digit) => digit + digit)
            .join('')
    }

    return /^[0-9a-f]{6}$/.test(value) ? `#${value}` : null
}

export function rgb(hex) {
    const value = normalize(hex).slice(1)

    return [0, 2, 4].map((start) => parseInt(value.slice(start, start + 2), 16))
}

export function luminance(hex) {
    const [red, green, blue] = rgb(hex).map((channel) => {
        const value = channel / 255

        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4
    })

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue
}

export function ratio(first, second) {
    const a = luminance(first)
    const b = luminance(second)

    return round((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05), 2)
}

/**
 * The theme for the Design tab's state, or `null` when the conversation
 * follows the panel or a value is unusable (such as a half-typed colour).
 *
 * @param {object} state The `theme` state of the builder.
 * @param {object} options `{ presets, fonts, radii }` from PHP.
 */
export function themeFrom(
    state,
    { presets = {}, fonts = {}, radii = {} } = {},
) {
    const preset = state?.preset ?? null

    if (!preset || preset === 'panel') {
        return null
    }

    const filled = (value) =>
        value !== null && value !== undefined && value !== ''
    const theme = {
        accent: '#4f46e5',
        background: '#ffffff',
        text: '#111827',
        answer: null,
        font: null,
        backgroundImage: null,
        overlay: 0,
        radius: 'medium',
    }

    for (const color of ['accent', 'background', 'text']) {
        if (filled(state[color])) {
            const value = normalize(state[color])

            if (value === null) {
                return null
            }

            theme[color] = value
        }
    }

    if (filled(state.answer)) {
        theme.answer = normalize(state.answer)

        if (theme.answer === null) {
            return null
        }
    }

    if (filled(state.font)) {
        if (!(state.font in fonts)) {
            return null
        }

        theme.font = state.font
    }

    const image = state.background_image

    theme.backgroundImage =
        image && typeof image === 'object'
            ? (Object.values(image)[0] ?? null)
            : filled(image)
              ? image
              : null

    theme.overlay = Math.max(
        0,
        Math.min(90, parseInt(state.overlay ?? 0, 10) || 0),
    )

    if (filled(state.radius)) {
        if (!(state.radius in radii)) {
            return null
        }

        theme.radius = state.radius
    }

    return theme
}

export function isDark(theme) {
    return luminance(theme.background) < 0.18
}

/**
 * Black or white, whichever reads better on the accent colour.
 */
export function accentContrast(theme) {
    return ratio(theme.accent, '#ffffff') >= ratio(theme.accent, '#0a0a0a')
        ? '#ffffff'
        : '#0a0a0a'
}

/**
 * The `font-family` value: the font, then a system fallback.
 */
export function fontStack(family, fonts = {}) {
    if (!family || !(family in fonts)) {
        return null
    }

    const fallback =
        fonts[family] === 'serif'
            ? 'ui-serif, Georgia, serif'
            : 'ui-sans-serif, system-ui, sans-serif'

    return `'${family}', ${fallback}`
}

/**
 * The stylesheet that loads the font, or `null` when external fonts are off.
 */
export function fontStylesheetUrl(
    family,
    { fonts = {}, fontsUrl = null } = {},
) {
    if (!fontsUrl || !family || !(family in fonts)) {
        return null
    }

    const slug = family
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')

    return `${fontsUrl}?family=${slug}:400,500,600,700&display=swap`
}

/**
 * The conversation's CSS variables, as `Theme::toCssVariables()` makes them.
 *
 * @param {(path: string) => string|null} resolveImage Turns an uploaded path into a URL.
 */
export function cssVariables(
    theme,
    { fonts = {}, radii = {}, resolveImage = (image) => image } = {},
) {
    const [red, green, blue] = rgb(theme.background)
    const image = theme.backgroundImage
        ? resolveImage(theme.backgroundImage)
        : null

    const variables = {
        '--cf-accent': theme.accent,
        '--cf-accent-contrast': accentContrast(theme),
        '--cf-bg': theme.background,
        '--cf-fg': theme.text,
        '--cf-muted': `color-mix(in oklab, ${theme.text} 65%, ${theme.background})`,
        '--cf-surface': `color-mix(in oklab, ${theme.background} 94%, ${theme.text})`,
        '--cf-answer-color': theme.answer ?? theme.text,
        '--cf-font-family': fontStack(theme.font, fonts),
        '--cf-radius': radii[theme.radius],
        '--cf-bg-image': image
            ? `url("${image.replace(/["\\]/g, '\\$&')}")`
            : null,
        '--cf-bg-overlay': image
            ? `rgb(${red} ${green} ${blue} / ${theme.overlay / 100})`
            : null,
    }

    return Object.fromEntries(
        Object.entries(variables).filter(
            ([, value]) => value !== null && value !== undefined,
        ),
    )
}

/**
 * Colour pairs that fall short of WCAG AA, as `{ check, ratio, minimum }`.
 */
export function contrastIssues(theme) {
    const checks = {
        text: [theme.text, theme.background, TEXT],
        answer: [theme.answer ?? theme.text, theme.background, TEXT],
        accent: [theme.accent, theme.background, NON_TEXT],
        button: [accentContrast(theme), theme.accent, TEXT],
    }

    return Object.entries(checks)
        .map(([check, [foreground, background, minimum]]) => ({
            check,
            ratio: ratio(foreground, background),
            minimum,
        }))
        .filter((issue) => issue.ratio < issue.minimum)
}

/**
 * A ratio as PHP's `number_format($ratio, 1)` writes it.
 */
export function formatRatio(value) {
    return round(value, 1).toFixed(1)
}
