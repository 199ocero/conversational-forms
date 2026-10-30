import {
    addDays,
    addMonths,
    clampDate,
    endOfWeek,
    initialDate,
    isMonthOutOfRange,
    isOutOfRange,
    monthWeeks,
    startOfWeek,
} from '../support/calendar.js'
import {
    compose,
    decompose,
    isDisabledOption,
    isValid,
    matchesTyped,
} from '../support/segments.js'

/**
 * Date (DD / MM / YYYY in any order) and time (HH : MM) answers typed into
 * separate boxes. Focus moves on as each box fills up, and Backspace in an
 * empty box moves back.
 *
 * Each box can also open a list of its values (a click or ↓), following the
 * ARIA 1.2 combobox pattern: typing narrows the list, Enter or a click picks
 * a value and opens the next box's list. While a list is open,
 * `aria-expanded="true"` tells the conversation to leave Enter alone.
 *
 * Dates can also be picked from a calendar under the boxes, following the
 * ARIA date picker dialog pattern: arrows move by day and week, Home / End to
 * the start and end of the week, Page Up / Page Down by month (with Shift, by
 * year), Enter or Space picks and Escape closes.
 */
export default function segmentsQuestionComponent({
    state,
    kind = 'date',
    segments = [],
    min = null,
    max = null,
    locale = undefined,
    firstDayOfWeek = 0,
    shouldAutoAdvance = false,
}) {
    let root = null
    let lastComposed = null

    const range = { min, max }
    const format = (options) => {
        try {
            return new Intl.DateTimeFormat(locale, {
                timeZone: 'UTC',
                ...options,
            })
        } catch {
            return new Intl.DateTimeFormat(undefined, {
                timeZone: 'UTC',
                ...options,
            })
        }
    }
    const monthTitle = format({ month: 'long', year: 'numeric' })
    const fullDate = format({ dateStyle: 'full' })
    const weekdayShort = format({ weekday: 'short' })
    const weekdayLong = format({ weekday: 'long' })
    const asDate = (value) => new Date(`${value}T00:00:00Z`)

    const today = () => {
        const now = new Date()

        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    }

    return {
        state,

        values: {},

        openIndex: null,

        activeIndex: null,

        typed: '',

        isCalendarOpen: false,

        // The day with keyboard focus in the calendar; its month is the one shown.
        calendarDate: null,

        // 4 January 1970 was a Sunday, so day `n` of the week is 4 + n January.
        weekdays: Array.from({ length: 7 }, (_, index) => {
            const date = asDate(
                `1970-01-${String(4 + ((firstDayOfWeek + index) % 7)).padStart(2, '0')}`,
            )

            return {
                short: weekdayShort.format(date),
                long: weekdayLong.format(date),
            }
        }),

        init() {
            root = this.$el
            this.values = decompose(kind, this.state, segments)

            // Only external changes (for example a value set on the server)
            // update the boxes; the value composed while typing never does.
            this.$watch('state', (value) => {
                if (value === lastComposed) {
                    return
                }

                const values = decompose(kind, value, segments)

                if (Object.keys(values).length) {
                    this.values = values
                }
            })
        },

        /**
         * What a box shows: its digits, or the locale's word for AM / PM.
         */
        displayValue(index) {
            const segment = segments[index]
            const value = this.values[segment.key] ?? ''

            if (segment.key !== 'meridiem') {
                return value
            }

            return (
                segment.options.find((option) => option.value === value)
                    ?.label ?? ''
            )
        },

        /**
         * The AM / PM box is never typed into: A or P (or the first letter of
         * the locale's words) chooses, Backspace clears.
         */
        handleMeridiemKeydown(index, event) {
            if (event.altKey || event.metaKey || event.ctrlKey) {
                return false
            }

            if (event.key === 'Backspace' || event.key === 'Delete') {
                if (!this.values.meridiem) {
                    return false
                }

                event.preventDefault()
                delete this.values.meridiem
                this.update()

                return true
            }

            if (event.key.length !== 1) {
                return false
            }

            const letter = event.key.toLowerCase()
            const option = segments[index].options.find(
                (option) =>
                    option.value.startsWith(letter) ||
                    option.label.toLowerCase().startsWith(letter),
            )

            if (!option) {
                return false
            }

            event.preventDefault()
            this.closeOptions()
            this.values.meridiem = option.value
            this.update()

            if (this.state === null && index < segments.length - 1) {
                this.focusSegment(index + 1)
            }

            return true
        },

        inputs() {
            return Array.from(root.querySelectorAll('[data-cf-segment]'))
        },

        focusSegment(index) {
            const input = this.inputs()[index]

            input?.focus()
            input?.select()
        },

        handleInput(index, event) {
            const segment = segments[index]
            const digits = event.target.value
                .replace(/\D/g, '')
                .slice(0, segment.length)

            event.target.value = digits
            this.values[segment.key] = digits
            this.update()

            if (this.openIndex === index) {
                this.typed = digits
                this.activeIndex = this.firstEnabledIndex(index)
            }

            if (
                digits.length === segment.length &&
                index < segments.length - 1
            ) {
                this.focusSegment(index + 1)
            }
        },

        handleKeydown(index, event) {
            if (this.handleOptionsKeydown(index, event)) {
                return
            }

            if (
                segments[index].key === 'meridiem' &&
                this.handleMeridiemKeydown(index, event)
            ) {
                return
            }

            const input = event.target
            const atStart =
                input.selectionStart === 0 && input.selectionEnd === 0
            const atEnd = input.selectionStart === input.value.length

            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                event.preventDefault()
                this.focusSegment(index - 1)

                return
            }

            if (event.key === 'ArrowLeft' && atStart && index > 0) {
                event.preventDefault()
                this.focusSegment(index - 1)

                return
            }

            if (
                ['ArrowRight', '/', '-', '.', ':'].includes(event.key) &&
                (atEnd || event.key !== 'ArrowRight') &&
                index < segments.length - 1
            ) {
                event.preventDefault()
                this.focusSegment(index + 1)
            }
        },

        update() {
            const value = compose(kind, this.values, segments)

            lastComposed = value
            this.state = value

            if (shouldAutoAdvance && value !== null && isValid(kind, value)) {
                root.dispatchEvent(
                    new CustomEvent('cf-answered', { bubbles: true }),
                )
            }
        },

        optionsFor(index) {
            const typed = index === this.openIndex ? this.typed : ''

            return (segments[index].options ?? []).filter((option) =>
                matchesTyped(option, typed),
            )
        },

        isDisabled(index, option) {
            return isDisabledOption(
                kind,
                segments[index].key,
                option.value,
                this.values,
                { min, max },
            )
        },

        isChosen(index, option) {
            return this.values[segments[index].key] === option.value
        },

        firstEnabledIndex(index) {
            const position = this.optionsFor(index).findIndex(
                (option) => !this.isDisabled(index, option),
            )

            return position === -1 ? null : position
        },

        openOptions(index) {
            if (!segments[index]?.options?.length || this.openIndex === index) {
                return
            }

            this.openIndex = index
            this.typed = ''

            const options = this.optionsFor(index)
            const current = String(new Date().getFullYear())
            const chosen = options.findIndex((option) =>
                this.isChosen(index, option),
            )
            // Years start at this year rather than a century ago.
            const thisYear =
                segments[index].key === 'year'
                    ? options.findIndex(
                          (option) =>
                              option.value === current &&
                              !this.isDisabled(index, option),
                      )
                    : -1

            this.activeIndex =
                chosen !== -1
                    ? chosen
                    : thisYear !== -1
                      ? thisYear
                      : this.firstEnabledIndex(index)

            this.$nextTick(() => this.scrollToActive(index, 'center'))
        },

        closeOptions() {
            this.openIndex = null
            this.activeIndex = null
            this.typed = ''
        },

        chooseOption(index, option) {
            if (this.isDisabled(index, option)) {
                return
            }

            this.values[segments[index].key] = option.value
            this.closeOptions()
            this.update()

            if (this.state !== null) {
                return
            }

            // Carry on to the next empty box, with its list open.
            const next = segments.findIndex(
                (segment, position) =>
                    position > index && !this.values[segment.key],
            )

            if (next !== -1) {
                this.focusSegment(next)
                this.openOptions(next)
            }
        },

        moveActive(index, offset) {
            const options = this.optionsFor(index)
            let position =
                this.activeIndex ?? (offset > 0 ? -1 : options.length)

            for (let step = 0; step < options.length; step++) {
                position = (position + offset + options.length) % options.length

                if (!this.isDisabled(index, options[position])) {
                    this.activeIndex = position
                    this.$nextTick(() => this.scrollToActive(index))

                    return
                }
            }
        },

        scrollToActive(index, block = 'nearest') {
            root.querySelectorAll('[data-cf-segment-options]')
                [index]?.querySelector('.cf-active-option')
                ?.scrollIntoView({ block })
        },

        /**
         * Keys for a box's list. Returns whether the key was handled.
         */
        handleOptionsKeydown(index, event) {
            if (
                !segments[index].options?.length ||
                event.altKey ||
                event.metaKey ||
                event.ctrlKey
            ) {
                return false
            }

            if (this.openIndex !== index) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault()
                    this.openOptions(index)

                    return true
                }

                return false
            }

            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowUp':
                    event.preventDefault()
                    this.moveActive(index, event.key === 'ArrowDown' ? 1 : -1)

                    return true
                case 'Enter': {
                    event.preventDefault()

                    const option =
                        this.optionsFor(index)[this.activeIndex ?? -1]

                    if (option) {
                        this.chooseOption(index, option)
                    } else {
                        this.closeOptions()
                    }

                    return true
                }
                case 'Escape':
                    event.preventDefault()
                    this.closeOptions()

                    return true
                case 'Tab':
                    this.closeOptions()

                    return false
            }

            return false
        },

        openCalendar() {
            this.closeOptions()
            this.calendarDate = initialDate(this.values, today(), range)
            this.isCalendarOpen = true
            this.$nextTick(() => this.focusCalendarDate())
        },

        closeCalendar(shouldReturnFocus = true) {
            if (!this.isCalendarOpen) {
                return
            }

            this.isCalendarOpen = false

            if (shouldReturnFocus) {
                this.$refs.calendarButton?.focus()
            }
        },

        toggleCalendar() {
            this.isCalendarOpen ? this.closeCalendar() : this.openCalendar()
        },

        calendarTitle() {
            return this.calendarDate
                ? monthTitle.format(asDate(this.calendarDate))
                : ''
        },

        calendarWeeks() {
            if (!this.calendarDate) {
                return []
            }

            const [year, month] = this.calendarDate.split('-').map(Number)
            const weeks = monthWeeks(year, month, firstDayOfWeek)

            // Always six weeks, so the calendar keeps its height and its cells.
            while (weeks.length < 6) {
                weeks.push(Array(7).fill(null))
            }

            return weeks
        },

        dayNumber(date) {
            return Number(date.slice(8))
        },

        dayLabel(date) {
            return fullDate.format(asDate(date))
        },

        isChosenDate(date) {
            return this.state === date
        },

        isToday(date) {
            return date === today()
        },

        isUnavailable(date) {
            return isOutOfRange(date, range)
        },

        canShowMonth(offset) {
            return (
                this.calendarDate !== null &&
                !isMonthOutOfRange(addMonths(this.calendarDate, offset), range)
            )
        },

        /**
         * The previous and next month buttons keep focus where it is, so
         * they can be pressed again.
         */
        showMonth(offset) {
            if (this.canShowMonth(offset)) {
                this.calendarDate = clampDate(
                    addMonths(this.calendarDate, offset),
                    range,
                )
            }
        },

        moveCalendarTo(date) {
            this.calendarDate = clampDate(date, range)
            this.$nextTick(() => this.focusCalendarDate())
        },

        focusCalendarDate() {
            root.querySelector(`[data-cf-date="${this.calendarDate}"]`)?.focus()
        },

        chooseDate(date) {
            if (this.isUnavailable(date)) {
                return
            }

            this.values = decompose(kind, date, segments)
            this.closeCalendar()
            this.update()
        },

        handleCalendarKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault()
                this.closeCalendar()

                return
            }

            if (!event.target.dataset?.cfDate) {
                return
            }

            const date = this.calendarDate

            // Pick the calendar's day rather than relying on the focused
            // button's click: right after a month change, focus can still be
            // on a button that has just been relabelled.
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                this.chooseDate(date)

                return
            }

            const target = {
                ArrowLeft: () => addDays(date, -1),
                ArrowRight: () => addDays(date, 1),
                ArrowUp: () => addDays(date, -7),
                ArrowDown: () => addDays(date, 7),
                Home: () => startOfWeek(date, firstDayOfWeek),
                End: () => endOfWeek(date, firstDayOfWeek),
                PageUp: () => addMonths(date, event.shiftKey ? -12 : -1),
                PageDown: () => addMonths(date, event.shiftKey ? 12 : 1),
            }[event.key]

            if (target) {
                event.preventDefault()
                this.moveCalendarTo(target())
            }
        },
    }
}
