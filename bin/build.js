import { spawn } from 'node:child_process'
import esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

/**
 * The stylesheet is compiled by the Tailwind CSS CLI so that `@apply` and
 * `--theme()` resolve against Tailwind's default theme. See resources/css/index.css.
 */
function compileStylesheet() {
    const args = [
        'tailwindcss',
        '--input',
        './resources/css/index.css',
        '--output',
        './resources/dist/conversational-forms.css',
        isDev ? '--watch' : '--minify',
    ]

    return new Promise((resolve, reject) => {
        const child = spawn('npx', args, {
            stdio: 'inherit',
            shell: process.platform === 'win32',
        })

        child.on('error', reject)
        child.on('exit', (code) =>
            code === 0
                ? resolve()
                : reject(new Error(`tailwindcss exited with code ${code}`)),
        )
    })
}

const defaultOptions = {
    define: {
        'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
    },
    bundle: true,
    mainFields: ['module', 'main'],
    platform: 'neutral',
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    treeShaking: true,
    target: ['es2020'],
    minify: !isDev,
    charset: 'ascii',
    plugins: [
        {
            name: 'watchPlugin',
            setup: function (build) {
                build.onStart(() => {
                    console.log(
                        `Build started at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                    )
                })

                build.onEnd((result) => {
                    if (result.errors.length > 0) {
                        console.log(
                            `Build failed at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                            result.errors,
                        )
                    } else {
                        console.log(
                            `Build finished at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                        )
                    }
                })
            },
        },
    ],
}

const alpineComponents = [
    'conversation',
    'choice-question',
    'dropdown-question',
    'segments-question',
    'ranking-question',
    'file-upload-question',
    'signature-question',
    'logic-map',
]

await Promise.all([
    ...alpineComponents.map((component) =>
        compile({
            ...defaultOptions,
            entryPoints: [`./resources/js/components/${component}.js`],
            outfile: `./resources/dist/components/${component}.js`,
        }),
    ),
    compileStylesheet(),
])
