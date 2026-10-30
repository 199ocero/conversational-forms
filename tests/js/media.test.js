import { describe, expect, it, vi } from 'vitest'
import mediaQuestionComponent from '../../resources/js/components/media-question.js'
import {
    describeMediaError,
    extensionFor,
    formatDuration,
    pickMimeType,
    recordingSupport,
} from '../../resources/js/support/media.js'

const translations = {
    requesting: 'requesting',
    recording: 'recording',
    uploading: 'uploading',
    done: 'done',
    denied: 'denied message',
    missing: 'missing message',
    busy: 'busy message',
    unsupported: 'unsupported message',
    failed: 'failed message',
    too_large: 'too large message',
}

/**
 * A browser with a microphone (or not), a MediaRecorder, timers driven by
 * the test, and Livewire's upload methods.
 */
function fakeEnvironment({ getUserMedia, hasMediaRecorder = true } = {}) {
    const track = { stop: vi.fn() }
    const stream = { getTracks: () => [track] }
    let tick = null

    class FakeRecorder {
        static isTypeSupported = (type) => type === 'audio/webm;codecs=opus'

        constructor(givenStream, options) {
            this.stream = givenStream
            this.options = options
            this.mimeType = options.mimeType
            this.state = 'inactive'
            FakeRecorder.last = this
        }

        start() {
            this.state = 'recording'
        }

        stop() {
            this.state = 'inactive'
            this.ondataavailable({
                data: new Blob(['sound'], { type: 'audio/webm' }),
            })
            this.onstop()
        }
    }

    return {
        track,
        stream,
        FakeRecorder,
        tick: () => tick?.(),
        environment: {
            navigator: {
                mediaDevices:
                    getUserMedia === null
                        ? undefined
                        : {
                              getUserMedia:
                                  getUserMedia ?? (async () => stream),
                          },
            },
            MediaRecorder: hasMediaRecorder ? FakeRecorder : undefined,
            setInterval: (callback) => {
                tick = callback
                return 1
            },
            clearInterval: () => {
                tick = null
            },
            URL: {
                createObjectURL: () => 'blob:preview',
                revokeObjectURL: vi.fn(),
            },
            crypto: { randomUUID: () => 'key-1' },
        },
    }
}

function recorder(environment, options = {}) {
    const component = mediaQuestionComponent({
        statePath: 'data.pitch',
        kind: 'audio',
        maxDuration: 3,
        maxSize: 10,
        translations,
        environment,
        ...options,
    })

    component.$wire = { upload: vi.fn(), cancelUpload: vi.fn() }
    component.$refs = {}
    component.$getUploadedFiles = async () => ({})
    component.$removeUploadedFile = vi.fn(async () => {})

    return component
}

describe('media helpers', () => {
    it('picks the first format the browser can record', () => {
        expect(
            pickMimeType('audio', (type) => type.startsWith('audio/mp4')),
        ).toBe('audio/mp4')
        expect(pickMimeType('video', (type) => type === 'video/webm')).toBe(
            'video/webm',
        )
        expect(pickMimeType('audio', () => false)).toBe('')
    })

    it('names files after their format', () => {
        expect(extensionFor('audio/webm;codecs=opus')).toBe('webm')
        expect(extensionFor('audio/mp4', 'audio')).toBe('m4a')
        expect(extensionFor('video/mp4', 'video')).toBe('mp4')
        expect(extensionFor('audio/ogg;codecs=opus')).toBe('ogg')
    })

    it('knows when a browser can record', () => {
        expect(recordingSupport(fakeEnvironment().environment)).toBe(
            'supported',
        )
        expect(
            recordingSupport(
                fakeEnvironment({ getUserMedia: null }).environment,
            ),
        ).toBe('unsupported')
        expect(
            recordingSupport(
                fakeEnvironment({ hasMediaRecorder: false }).environment,
            ),
        ).toBe('unsupported')
    })

    it('tells permission problems from missing or busy devices', () => {
        expect(describeMediaError({ name: 'NotAllowedError' })).toBe('denied')
        expect(describeMediaError({ name: 'SecurityError' })).toBe('denied')
        expect(describeMediaError({ name: 'NotFoundError' })).toBe('missing')
        expect(describeMediaError({ name: 'NotReadableError' })).toBe('busy')
        expect(describeMediaError(new Error('?'))).toBe('failed')
    })

    it('formats durations', () => {
        expect(formatDuration(0)).toBe('0:00')
        expect(formatDuration(75)).toBe('1:15')
        expect(formatDuration(-3)).toBe('0:00')
    })
})

describe('recording an answer', () => {
    it('offers an upload when the browser cannot record', async () => {
        const component = recorder(
            fakeEnvironment({ getUserMedia: null }).environment,
        )

        await component.init()

        expect(component.status).toBe('unsupported')
        expect(component.isProblem).toBe(true)
    })

    it('explains a blocked microphone', async () => {
        const component = recorder(
            fakeEnvironment({
                getUserMedia: async () => {
                    throw Object.assign(new Error('blocked'), {
                        name: 'NotAllowedError',
                    })
                },
            }).environment,
        )

        await component.start()

        expect(component.status).toBe('denied')
        expect(component.message).toBe('denied message')
        expect(component.$wire.upload).not.toHaveBeenCalled()
    })

    it('explains a missing microphone', async () => {
        const component = recorder(
            fakeEnvironment({
                getUserMedia: async () => {
                    throw Object.assign(new Error('none'), {
                        name: 'NotFoundError',
                    })
                },
            }).environment,
        )

        await component.start()

        expect(component.status).toBe('missing')
    })

    it('records, stops at the time limit and uploads like a file', async () => {
        const fake = fakeEnvironment()
        const component = recorder(fake.environment)

        await component.start()

        expect(component.status).toBe('recording')
        expect(fake.FakeRecorder.last.options.mimeType).toBe(
            'audio/webm;codecs=opus',
        )

        fake.tick()
        fake.tick()
        expect(component.elapsedLabel).toBe('0:02 / 0:03')

        fake.tick()
        await Promise.resolve()

        expect(fake.track.stop).toHaveBeenCalled()
        expect(component.status).toBe('uploading')
        expect(component.previewUrl).toBe('blob:preview')

        const [path, file, onDone] = component.$wire.upload.mock.calls[0]

        expect(path).toBe('data.pitch.key-1')
        expect(file.name).toMatch(/^audio-.+\.webm$/)
        expect(file.type).toBe('audio/webm')

        onDone()

        expect(component.status).toBe('done')
        expect(component.message).toBe('done')
    })

    it('refuses recordings over the size limit before uploading', async () => {
        const component = recorder(fakeEnvironment().environment, {
            maxSize: 1,
        })

        await component.upload(
            new File(['x'.repeat(2048)], 'long.webm', { type: 'audio/webm' }),
        )

        expect(component.status).toBe('too_large')
        expect(component.$wire.upload).not.toHaveBeenCalled()
    })

    it('replaces the previous recording when recording again', async () => {
        const fake = fakeEnvironment()
        const component = recorder(fake.environment)

        await component.start()
        component.stop()
        await Promise.resolve()
        component.$wire.upload.mock.calls[0][2]()

        await component.again()

        expect(component.$removeUploadedFile).toHaveBeenCalledWith({
            fileKey: 'key-1',
        })
        expect(component.status).toBe('recording')
    })

    it('shows a recording that was already saved', async () => {
        const component = recorder(fakeEnvironment().environment)
        component.$getUploadedFiles = async () => ({
            abc: { name: 'audio-earlier.webm', size: 100 },
        })

        await component.init()

        expect(component.status).toBe('done')
        expect(component.fileName).toBe('audio-earlier.webm')
        expect(component.fileKey).toBe('abc')
    })
})
