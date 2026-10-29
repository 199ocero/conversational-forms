/**
 * Draw a signature with a mouse, pen or finger, or type it. The result is
 * sent as a PNG data URL; the server checks and stores it.
 */
export default function signatureQuestionComponent({
    state,
    translations = {},
}) {
    let root = null
    let canvas = null
    let context = null
    let isDrawing = false
    let lastPoint = null

    return {
        state,

        mode: 'draw',

        typedName: '',

        hasDrawing: false,

        init() {
            root = this.$el
            canvas = root.querySelector('canvas')
            context = canvas.getContext('2d')

            this.resize()
            new ResizeObserver(() => this.resize()).observe(canvas)
            window.addEventListener('cf-step-shown', () => this.resize())

            this.$watch('typedName', () => this.renderTypedName())
        },

        resize() {
            const ratio = window.devicePixelRatio || 1
            const { width, height } = canvas.getBoundingClientRect()

            if (!width || !height) {
                return
            }

            const snapshot = this.state

            canvas.width = width * ratio
            canvas.height = height * ratio
            context.setTransform(ratio, 0, 0, ratio, 0, 0)
            context.lineCap = 'round'
            context.lineJoin = 'round'
            context.lineWidth = 2.5
            context.strokeStyle = getComputedStyle(canvas).color

            if (
                typeof snapshot === 'string' &&
                snapshot.startsWith('data:image/png')
            ) {
                const image = new Image()

                image.onload = () =>
                    context.drawImage(image, 0, 0, width, height)
                image.src = snapshot
                this.hasDrawing = true
            }
        },

        point(event) {
            const rect = canvas.getBoundingClientRect()

            return { x: event.clientX - rect.left, y: event.clientY - rect.top }
        },

        start(event) {
            if (this.mode !== 'draw') {
                return
            }

            event.preventDefault()
            canvas.setPointerCapture?.(event.pointerId)
            isDrawing = true
            lastPoint = this.point(event)

            context.beginPath()
            context.arc(
                lastPoint.x,
                lastPoint.y,
                context.lineWidth / 2,
                0,
                Math.PI * 2,
            )
            context.fillStyle = context.strokeStyle
            context.fill()
        },

        draw(event) {
            if (!isDrawing) {
                return
            }

            const point = this.point(event)
            const middle = {
                x: (lastPoint.x + point.x) / 2,
                y: (lastPoint.y + point.y) / 2,
            }

            context.beginPath()
            context.moveTo(lastPoint.x, lastPoint.y)
            context.quadraticCurveTo(
                lastPoint.x,
                lastPoint.y,
                middle.x,
                middle.y,
            )
            context.lineTo(point.x, point.y)
            context.stroke()

            lastPoint = point
        },

        end() {
            if (!isDrawing) {
                return
            }

            isDrawing = false
            this.hasDrawing = true
            this.save()
        },

        save() {
            this.state = canvas.toDataURL('image/png')
        },

        clear() {
            const { width, height } = canvas.getBoundingClientRect()

            context.clearRect(0, 0, width, height)
            this.hasDrawing = false
            this.typedName = ''
            this.state = null
        },

        toggleMode() {
            this.clear()
            this.mode = this.mode === 'draw' ? 'type' : 'draw'

            this.$nextTick(() => {
                if (this.mode === 'type') {
                    root.querySelector('[data-cf-signature-name]')?.focus()
                }
            })
        },

        renderTypedName() {
            if (this.mode !== 'type') {
                return
            }

            const { width, height } = canvas.getBoundingClientRect()
            const name = this.typedName.trim()

            context.clearRect(0, 0, width, height)

            if (name === '') {
                this.hasDrawing = false
                this.state = null

                return
            }

            let size = Math.min(56, height * 0.5)

            context.fillStyle = getComputedStyle(canvas).color
            context.textAlign = 'center'
            context.textBaseline = 'middle'

            do {
                context.font = `${size}px ${translations.font ?? 'cursive'}`
                size -= 2
            } while (context.measureText(name).width > width * 0.9 && size > 12)

            context.fillText(name, width / 2, height / 2)
            this.hasDrawing = true
            this.save()
        },
    }
}
