import { isSameValue } from '../support/choices.js'

const normalize = (value) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()

/**
 * A searchable list following the ARIA 1.2 combobox pattern. While the list
 * is open, `aria-expanded="true"` tells the conversation to leave Enter alone.
 */
export default function dropdownQuestionComponent({
    state,
    options = [],
    shouldAutoAdvance = false,
}) {
    let root = null

    const labelFor = (value) =>
        options.find((option) => isSameValue(option.value, value))?.label ?? ''

    return {
        state,

        query: '',

        isOpen: false,

        activeIndex: null,

        init() {
            root = this.$el
            this.query = labelFor(this.state)

            this.$watch('state', (value) => {
                if (!this.isOpen) {
                    this.query = labelFor(value)
                }
            })
        },

        get filteredOptions() {
            const query = normalize(this.query)

            if (query === '' || query === normalize(labelFor(this.state))) {
                return options
            }

            return options.filter((option) =>
                normalize(option.label).includes(query),
            )
        },

        isSelected(value) {
            return isSameValue(this.state, value)
        },

        open() {
            if (this.isOpen) {
                return
            }

            this.isOpen = true

            const selectedIndex = this.filteredOptions.findIndex((option) =>
                this.isSelected(option.value),
            )

            this.activeIndex = selectedIndex === -1 ? 0 : selectedIndex
            this.$nextTick(() => this.scrollToActive())
        },

        close() {
            this.isOpen = false
            this.activeIndex = null
            this.query = labelFor(this.state)
        },

        choose(option) {
            this.state = option.value
            this.close()

            if (shouldAutoAdvance) {
                root.dispatchEvent(
                    new CustomEvent('cf-answered', { bubbles: true }),
                )
            }
        },

        move(offset) {
            if (!this.isOpen) {
                this.open()

                return
            }

            const count = this.filteredOptions.length

            if (count === 0) {
                return
            }

            this.activeIndex =
                ((this.activeIndex ?? -1) + offset + count) % count
            this.$nextTick(() => this.scrollToActive())
        },

        scrollToActive() {
            this.$refs.listbox
                ?.querySelector('.cf-active-option')
                ?.scrollIntoView({ block: 'nearest' })
        },

        handleKeydown(event) {
            if (event.altKey || event.metaKey || event.ctrlKey) {
                return
            }

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault()
                    this.move(1)
                    break
                case 'ArrowUp':
                    event.preventDefault()
                    this.move(-1)
                    break
                case 'Enter': {
                    const option = this.filteredOptions[this.activeIndex ?? -1]

                    if (this.isOpen && option) {
                        event.preventDefault()
                        this.choose(option)
                    }

                    break
                }
                case 'Escape':
                    if (this.isOpen) {
                        event.preventDefault()
                        this.close()
                    }

                    break
                case 'Tab':
                    this.close()
                    break
            }
        },
    }
}
