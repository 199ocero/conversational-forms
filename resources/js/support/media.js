/**
 * Pure helpers for recorded answers, shared by the Alpine component and the
 * tests.
 */

/**
 * Formats to try, best first. Chrome and Firefox record WebM, Safari MP4.
 */
export const CANDIDATE_TYPES = {
    audio: [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4',
        'audio/ogg;codecs=opus',
    ],
    video: [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
        'video/mp4',
    ],
}

/**
 * The first format the browser can record, or `''` to let it choose.
 */
export function pickMimeType(kind, isTypeSupported) {
    return (
        (CANDIDATE_TYPES[kind] ?? []).find((type) => isTypeSupported(type)) ??
        ''
    )
}

/**
 * A file extension for a recording's MIME type.
 */
export function extensionFor(mimeType, kind = 'audio') {
    const type = String(mimeType).split(';')[0]

    if (type.endsWith('/mp4')) {
        return kind === 'audio' ? 'm4a' : 'mp4'
    }

    if (type.endsWith('/ogg')) {
        return 'ogg'
    }

    return 'webm'
}

/**
 * Whether this browser can record: it needs `getUserMedia` (which also
 * needs HTTPS) and `MediaRecorder`.
 *
 * @returns {'supported' | 'unsupported'}
 */
export function recordingSupport(environment = globalThis) {
    const hasGetUserMedia =
        typeof environment.navigator?.mediaDevices?.getUserMedia === 'function'

    return hasGetUserMedia && typeof environment.MediaRecorder === 'function'
        ? 'supported'
        : 'unsupported'
}

/**
 * What went wrong when asking for the microphone or camera.
 *
 * @returns {'denied' | 'missing' | 'busy' | 'failed'}
 */
export function describeMediaError(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
        case 'SecurityError':
            return 'denied'
        case 'NotFoundError':
        case 'DevicesNotFoundError':
        case 'OverconstrainedError':
            return 'missing'
        case 'NotReadableError':
        case 'TrackStartError':
        case 'AbortError':
            return 'busy'
        default:
            return 'failed'
    }
}

/**
 * Seconds as `m:ss`.
 */
export function formatDuration(seconds) {
    const total = Math.max(0, Math.floor(seconds))

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
}
