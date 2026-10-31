{{-- Text with piping tokens such as {name} shown as chips. `$text` is a JavaScript expression. Never rendered as HTML. --}}
<template x-for="(part, index) in pipe({{ $text }})" x-bind:key="index"><span x-bind:class="{ 'cf-editor-token': part.token }" x-bind:title="part.token ? tokenLabel(part.token) : null" x-text="part.token ? `{${part.token}}` : part.text"></span></template>
