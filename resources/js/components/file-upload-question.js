/**
 * A drop zone on top of Filament's file upload pipeline: files are sent with
 * Livewire's uploads to `{statePath}.{fileKey}`, exactly as Filament's own
 * upload component does, and removed with its `removeUploadedFile()` method.
 */
export default function fileUploadQuestionComponent({
    statePath,
    isMultiple = false,
    maxFiles = null,
    maxSize = null,
    acceptedFileTypes = null,
    translations = {},
}) {
    const uuid = () =>
        crypto.randomUUID?.() ??
        `${Date.now()}-${Math.random().toString(16).slice(2)}`

    const formatSize = (bytes) =>
        bytes >= 1024 * 1024
            ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
            : `${Math.max(1, Math.round(bytes / 1024))} KB`

    const isAccepted = (file) => {
        if (!acceptedFileTypes?.length) {
            return true
        }

        return acceptedFileTypes.some((type) => {
            if (type.endsWith('/*')) {
                return file.type.startsWith(type.slice(0, -1))
            }

            if (type.startsWith('.')) {
                return file.name.toLowerCase().endsWith(type.toLowerCase())
            }

            return file.type === type
        })
    }

    return {
        files: [],

        isDragging: false,

        message: '',

        problem: '',

        async init() {
            const existing = (await this.$getUploadedFiles()) ?? {}

            this.files = Object.entries(existing)
                .filter(([, file]) => file)
                .map(([key, file]) => ({
                    key,
                    name: file.name,
                    size: formatSize(file.size ?? 0),
                    progress: 100,
                    status: 'done',
                }))
        },

        get isUploading() {
            return this.files.some((file) => file.status === 'uploading')
        },

        handleSelect(event) {
            this.add(Array.from(event.target.files ?? []))
            event.target.value = ''
        },

        handleDrop(event) {
            this.isDragging = false
            this.add(Array.from(event.dataTransfer?.files ?? []))
        },

        async add(selected) {
            this.problem = ''

            if (!isMultiple && selected.length) {
                for (const file of [...this.files]) {
                    await this.remove(file)
                }

                selected = selected.slice(0, 1)
            }

            for (const file of selected) {
                if (maxFiles && this.files.length >= maxFiles) {
                    this.problem = translations.too_many
                    break
                }

                if (!isAccepted(file)) {
                    this.problem = translations.wrong_type.replace(
                        ':name',
                        file.name,
                    )
                    continue
                }

                if (maxSize && file.size > maxSize * 1024) {
                    this.problem = translations.too_large.replace(
                        ':name',
                        file.name,
                    )
                    continue
                }

                this.upload(file)
            }
        },

        upload(file) {
            const entry = {
                key: uuid(),
                name: file.name,
                size: formatSize(file.size),
                progress: 0,
                status: 'uploading',
            }

            this.files.push(entry)

            const tracked = () =>
                this.files.find((item) => item.key === entry.key)

            this.$wire.upload(
                `${statePath}.${entry.key}`,
                file,
                () => {
                    Object.assign(tracked() ?? {}, {
                        progress: 100,
                        status: 'done',
                    })
                    this.message = translations.uploaded.replace(
                        ':name',
                        file.name,
                    )
                },
                () => {
                    Object.assign(tracked() ?? {}, { status: 'error' })
                    this.problem = translations.failed.replace(
                        ':name',
                        file.name,
                    )
                },
                (event) => {
                    Object.assign(tracked() ?? {}, {
                        progress: event.detail.progress,
                    })
                },
            )
        },

        async remove(file) {
            if (file.status === 'uploading') {
                this.$wire.cancelUpload(`${statePath}.${file.key}`)
            } else if (file.status === 'done') {
                await this.$removeUploadedFile({ fileKey: file.key })
            }

            this.files = this.files.filter((item) => item.key !== file.key)
            this.message = translations.removed.replace(':name', file.name)
        },
    }
}
