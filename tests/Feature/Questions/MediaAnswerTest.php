<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Questions\AudioAnswer;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\VideoAnswer;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;

beforeEach(fn () => Storage::fake('public'));

describe('AudioAnswer and VideoAnswer', function () {
    it('offers to record, with what will happen and a way to upload instead', function () {
        conversation(Conversation::make([AudioAnswer::make('pitch')->maxDuration(90)]))
            ->assertSee(['Start recording', 'Up to 01:30. Your browser will ask to use your microphone.', 'Upload a file instead'])
            ->assertSeeHtml('accept="audio/*,video/webm,video/mp4"')
            ->assertSeeHtml('mediaQuestionComponent');

        conversation(Conversation::make([VideoAnswer::make('intro')]))
            ->assertSee('Up to 01:00. Your browser will ask to use your camera and microphone.')
            ->assertSeeHtml('x-ref="live"')
            ->assertSeeHtml('accept="video/*"');
    });

    it('explains every way recording can fail', function () {
        $html = conversation(Conversation::make([AudioAnswer::make('pitch')]))->html();

        // The messages reach the recorder as JSON, one per way it can fail.
        foreach (['allow the microphone', 'No microphone was found', 'in use by another app', 'record here. Upload a file instead', 'please try again', 'is too large'] as $text) {
            expect(strtolower($html))->toContain(strtolower($text));
        }
    });

    it('can hide the upload alternative where recording works', function () {
        conversation(Conversation::make([AudioAnswer::make('pitch')->allowUpload(false)]))
            ->assertSeeHtml("x-show=\"status === 'unsupported'\"");
    });

    it('has sensible limits', function () {
        expect(AudioAnswer::make('a'))
            ->getMaxDuration()->toBe(120)
            ->getMaxSize()->toBe(5 * 1024)
            ->isMultiple()->toBeFalse()
            ->shouldAutoAdvance()->toBeFalse()
            ->and(VideoAnswer::make('v'))
            ->getMaxDuration()->toBe(60)
            ->getMaxSize()->toBe(12 * 1024);
    });

    it('stores recordings with Filament, like any upload', function () {
        conversation(Conversation::make([AudioAnswer::make('pitch')->disk('public')->directory('pitches')->required()]))
            ->set('data.pitch.recording', UploadedFile::fake()->create('audio-2026.webm', 300, 'audio/webm'))
            ->submitConversation()
            ->assertConversationCompleted();

        $path = TestConversation::$submissions[0]['data']['pitch'];

        expect($path)->toStartWith('pitches/');
        Storage::disk('public')->assertExists($path);
    });

    it('checks the type and size on the server', function (UploadedFile $file, string $message) {
        $livewire = conversation(Conversation::make([AudioAnswer::make('pitch')->disk('public')->required(), ShortText::make('next')]))
            ->set('data.pitch.recording', $file)
            ->advanceConversation('pitch');

        expect($livewire->errors()->first('data.pitch'))->toContain($message);
    })->with([
        'not audio' => [fn () => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'must be a file of type: audio/*, video/webm, video/mp4'],
        'too large' => [fn () => UploadedFile::fake()->create('long.webm', 6 * 1024, 'audio/webm'), 'must not be greater than 5120 kilobytes'],
    ]);

    it('accepts audio that the server sees as a WebM or MP4 video', function (string $name, string $mime) {
        conversation(Conversation::make([AudioAnswer::make('pitch')->disk('public')->required(), ShortText::make('next')]))
            ->set('data.pitch.recording', UploadedFile::fake()->create($name, 50, $mime))
            ->advanceConversation('pitch')
            ->assertHasNoErrors();
    })->with([
        'WebM seen as video' => ['audio-1.webm', 'video/webm'],
        'M4A seen as video' => ['audio-1.m4a', 'video/mp4'],
        'Ogg' => ['audio-1.ogg', 'audio/ogg'],
        'MP3' => ['audio-1.mp3', 'audio/mpeg'],
    ]);

    it('is stored and edited like the other questions', function () {
        $registry = app(QuestionTypeRegistry::class);

        expect($registry->forQuestion(AudioAnswer::make('a'))->getName())->toBe('audio_answer')
            ->and($registry->forQuestion(VideoAnswer::make('v'))->getName())->toBe('video_answer');

        $definition = VideoAnswer::make('intro')->label('Say hi')->maxDuration(30)->maxSize(8000)->allowUpload(false)->toDefinition();

        expect($definition['settings'])->toBe(['max_duration' => 30, 'max_size' => 8000, 'allow_upload' => false])
            ->and(VideoAnswer::make('intro')->fillFromDefinition($definition)->toDefinition())->toBe($definition);
    });
});
