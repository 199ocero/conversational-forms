<?php

namespace JaOcero\ConversationalForms\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Js;

/**
 * The script that embeds conversations in other websites:
 *
 *     <div data-conversational-form="feedback"></div>
 *     <script src="https://your-app.test/f/embed.js" async></script>
 *
 * Each element gets an iframe of the conversation, which grows with its
 * content. Only height messages from this app are listened to.
 */
class EmbedScriptController
{
    public function __invoke(): Response
    {
        $base = Js::from(url(config('conversational-forms.routes.prefix', 'f')));
        $origin = Js::from(url('/'));
        $title = Js::from(__('conversational-forms::conversational-forms.share.iframe_title'));

        $script = <<<JS
            (() => {
                const base = {$base}
                const origin = new URL({$origin}).origin

                const embed = () => {
                    document.querySelectorAll('[data-conversational-form]:not([data-cf-embedded])').forEach((element) => {
                        element.setAttribute('data-cf-embedded', '')

                        const iframe = document.createElement('iframe')
                        iframe.src = base + '/' + encodeURIComponent(element.dataset.conversationalForm) + '?embed=1'
                        iframe.title = element.dataset.title || {$title}
                        iframe.loading = 'lazy'
                        iframe.allow = 'camera; microphone; clipboard-write'
                        iframe.style.cssText = 'display:block;width:100%;border:0;min-height:' + (parseInt(element.dataset.minHeight, 10) || 420) + 'px'
                        element.appendChild(iframe)
                    })
                }

                window.addEventListener('message', (event) => {
                    if (event.origin !== origin || event.data?.type !== 'conversational-forms:resize') {
                        return
                    }

                    document.querySelectorAll('[data-cf-embedded] > iframe').forEach((iframe) => {
                        if (iframe.contentWindow === event.source) {
                            iframe.style.height = Math.max(0, Number(event.data.height) || 0) + 'px'
                        }
                    })
                })

                document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', embed) : embed()
            })()
            JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
