import { describe, expect, it } from 'vitest'
import {
    contrastIssues,
    cssVariables,
    fontStylesheetUrl,
    formatRatio,
    isDark,
    normalize,
    themeFrom,
} from '../../resources/js/support/theme.js'
import cases from '../Fixtures/themes/cases.json'

const fonts = {
    Inter: 'sans-serif',
    'DM Sans': 'sans-serif',
    Nunito: 'sans-serif',
    Poppins: 'sans-serif',
    'Space Grotesk': 'sans-serif',
    'IBM Plex Sans': 'sans-serif',
    Lora: 'serif',
    Merriweather: 'serif',
    'Playfair Display': 'serif',
    'Source Serif 4': 'serif',
}

const radii = {
    none: '0px',
    small: '0.25rem',
    medium: '0.5rem',
    large: '1rem',
    full: '1.75rem',
}

describe('themes (shared with PHP)', () => {
    it.each(cases)('$note', ({ state, expected }) => {
        const theme = themeFrom(state, { fonts, radii })

        if (expected === null) {
            expect(theme).toBeNull()

            return
        }

        expect(cssVariables(theme, { fonts, radii })).toEqual(
            expected.variables,
        )
        expect(isDark(theme)).toBe(expected.is_dark)
        expect(contrastIssues(theme)).toEqual(expected.issues)
    })
})

describe('colours', () => {
    it('normalizes hex colours', () => {
        expect(normalize(' #ABC ')).toBe('#aabbcc')
        expect(normalize('4f46e5')).toBe('#4f46e5')
        expect(normalize('#12')).toBeNull()
        expect(normalize(null)).toBeNull()
    })

    it('writes ratios like PHP', () => {
        expect(formatRatio(4.48)).toBe('4.5')
        expect(formatRatio(1.04)).toBe('1.0')
        expect(formatRatio(1.25)).toBe('1.3')
    })
})

describe('fonts', () => {
    it('links to the font stylesheet', () => {
        expect(
            fontStylesheetUrl('IBM Plex Sans', {
                fonts,
                fontsUrl: 'https://fonts.bunny.net/css',
            }),
        ).toBe(
            'https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap',
        )
    })

    it('loads nothing when external fonts are off or the font is unknown', () => {
        expect(fontStylesheetUrl('Inter', { fonts, fontsUrl: null })).toBeNull()
        expect(
            fontStylesheetUrl('Comic Sans', {
                fonts,
                fontsUrl: 'https://fonts.bunny.net/css',
            }),
        ).toBeNull()
    })
})
