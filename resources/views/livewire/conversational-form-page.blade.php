@php
    $captcha = $this->getCaptcha();
@endphp

<main @class(['cf-public-page', 'cf-embedded' => $isEmbedded])>
    {{ $this->form }}

    @if (config('conversational-forms.spam.honeypot', true))
        {{-- Hidden from people (and screen readers); bots that fill every field fill this one. --}}
        <div class="cf-honeypot" aria-hidden="true">
            <label for="cf-website">Website</label>
            <input id="cf-website" type="text" name="website" wire:model="website" tabindex="-1" autocomplete="off" />
        </div>
    @endif

    @if ($captcha)
        {{ $captcha->render() }}

        @error('captcha')
            <p role="alert" class="cf-captcha-error">{{ $message }}</p>
        @enderror
    @endif
</main>
