import {
    describeMediaError,
    extensionFor,
    formatDuration,
    pickMimeType,
    recordingSupport,
} from '../support/media.js'

/**
 * Records an answer with the microphone or camera and uploads it with
 * Livewire's uploads, exactly like `FileUpload`. Where recording isn't
 * possible (no permission, no device, an old browser, or a page that isn't
 * on HTTPS), a file can be uploaded instead.
 *
 * Statuses: `idle`, `requesting`, `recording`, `uploading`, `done`, and the
 * problems `denied`, `missing`, `busy`, `unsupported`, `failed`, `too_large`.
 */
export default function mediaQuestionComponent({
    statePath,
    kind = 'audio',
    maxDuration = 120,
    maxSize = null,
    translations = {},
    environment = globalThis,
}) {
    let stream = null
    let recorder = null
    let timer = null

    const uuid = () =>
        environment.crypto?.randomUUID?.() ??
        `${Date.now()}-${Math.random().toString(16).slice(2)}`

    return {
        status: 'idle',

        elapsed: 0,

        progress: 0,

        previewUrl: null,

        fileKey: null,

        fileName: null,

        message: '',

        async init() {
            if (recordingSupport(environment) === 'unsupported') {
                this.status = 'unsupported'
            }

            const existing = Object.entries(
                (await this.$getUploadedFiles?.()) ?? {},
            ).find(([, file]) => file)

            if (existing) {
                ;[this.fileKey] = existing
                this.fileName = existing[1].name
                this.status = 'done'
            }
        },

        destroy() {
            this.release()
            this.revokePreview()
        },

        get remaining() {
            return formatDuration(maxDuration - this.elapsed)
        },

        get elapsedLabel() {
            return `${formatDuration(this.elapsed)} / ${formatDuration(maxDuration)}`
        },

        get isProblem() {
            return [
                'denied',
                'missing',
                'busy',
                'unsupported',
                'failed',
                'too_large',
            ].includes(this.status)
        },

        async start() {
            this.status = 'requesting'
            this.announce('requesting')

            try {
                stream = await environment.navigator.mediaDevices.getUserMedia(
                    kind === 'video'
                        ? {
                              audio: true,
                              video: {
                                  width: { ideal: 1280 },
                                  height: { ideal: 720 },
                              },
                          }
                        : { audio: true },
                )
            } catch (error) {
                this.status = describeMediaError(error)
                this.announce(this.status)

                return
            }

            const mimeType = pickMimeType(
                kind,
                (type) =>
                    environment.MediaRecorder.isTypeSupported?.(type) ?? false,
            )
            const chunks = []

            recorder = new environment.MediaRecorder(stream, {
                ...(mimeType ? { mimeType } : {}),
                audioBitsPerSecond: 64_000,
                ...(kind === 'video' ? { videoBitsPerSecond: 1_000_000 } : {}),
            })

            recorder.ondataavailable = (event) => {
                if (event.data?.size) {
                    chunks.push(event.data)
                }
            }

            recorder.onstop = () => {
                const type = recorder.mimeType || mimeType || `${kind}/webm`
                const file = new File(
                    chunks,
                    `${kind}-${new Date().toISOString().replace(/[:.]/g, '-')}.${extensionFor(type, kind)}`,
                    { type: type.split(';')[0] },
                )

                this.release()
                this.upload(file)
            }

            if (kind === 'video' && this.$refs.live) {
                this.$refs.live.srcObject = stream
            }

            this.elapsed = 0
            this.status = 'recording'
            this.announce('recording')
            recorder.start(1000)

            timer = environment.setInterval(() => {
                this.elapsed++

                if (this.elapsed >= maxDuration) {
                    this.stop()
                }
            }, 1000)
        },

        stop() {
            environment.clearInterval(timer)
            timer = null

            if (recorder?.state === 'recording') {
                recorder.stop()
            }
        },

        async again() {
            await this.discard()
            await this.start()
        },

        handleSelect(event) {
            const [file] = Array.from(event.target.files ?? [])
            event.target.value = ''

            if (file) {
                this.upload(file)
            }
        },

        async upload(file) {
            if (maxSize && file.size > maxSize * 1024) {
                this.status = 'too_large'
                this.announce('too_large')

                return
            }

            await this.discard()

            this.revokePreview()
            this.previewUrl = environment.URL?.createObjectURL?.(file) ?? null
            this.fileName = file.name
            this.fileKey = uuid()
            this.progress = 0
            this.status = 'uploading'
            this.announce('uploading')

            this.$wire.upload(
                `${statePath}.${this.fileKey}`,
                file,
                () => {
                    this.status = 'done'
                    this.announce('done')
                },
                () => {
                    this.status = 'failed'
                    this.announce('failed')
                },
                (event) => {
                    this.progress = event.detail.progress
                },
            )
        },

        /**
         * Remove the uploaded recording, if any.
         */
        async discard() {
            if (this.fileKey === null) {
                return
            }

            const key = this.fileKey
            this.fileKey = null

            if (this.status === 'uploading') {
                this.$wire.cancelUpload(`${statePath}.${key}`)
            } else {
                await this.$removeUploadedFile?.({ fileKey: key })
            }
        },

        release() {
            environment.clearInterval(timer)
            timer = null
            stream?.getTracks().forEach((track) => track.stop())
            stream = null

            if (this.$refs?.live) {
                this.$refs.live.srcObject = null
            }
        },

        revokePreview() {
            if (this.previewUrl) {
                environment.URL?.revokeObjectURL?.(this.previewUrl)
                this.previewUrl = null
            }
        },

        /**
         * Chrome records WebM without a duration, so players show "0:00 /
         * Infinity" and can't seek. Seeking to the end once makes the browser
         * measure it.
         */
        measure(element) {
            if (element.duration !== Infinity) {
                return
            }

            element.addEventListener(
                'timeupdate',
                () => {
                    element.currentTime = 0
                },
                { once: true },
            )
            element.currentTime = Number.MAX_SAFE_INTEGER
        },

        announce(key) {
            this.message = translations[key] ?? ''
        },
    }
}
