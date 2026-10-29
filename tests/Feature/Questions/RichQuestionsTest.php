<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\Address;
use JaOcero\ConversationalForms\Questions\ContactInfo;
use JaOcero\ConversationalForms\Questions\DateQuestion;
use JaOcero\ConversationalForms\Questions\FileUpload;
use JaOcero\ConversationalForms\Questions\Matrix;
use JaOcero\ConversationalForms\Questions\Ranking;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\Signature;
use JaOcero\ConversationalForms\Questions\TimeQuestion;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use Livewire\Features\SupportTesting\Testable;

function submitted(string $field): mixed
{
    return TestConversation::$submissions[0]['data'][$field] ?? null;
}

function errorFor(Testable $livewire, string $key): ?string
{
    return $livewire->errors()->first($key) ?: null;
}

describe('DateQuestion', function () {
    it('renders day, month and year boxes in the chosen order', function () {
        conversation(Conversation::make([DateQuestion::make('start')->format('MDY')->minDate('2026-01-01')]))
            ->assertSeeInOrder(['Month', 'Day', 'Year'])
            ->assertSeeHtml('inputmode="numeric"')
            ->assertSeeHtml('type="date"')
            ->assertSeeHtml('min="2026-01-01"');
    });

    it('rejects dates that do not exist or are out of range', function (string $date, ?string $message) {
        $livewire = conversation(Conversation::make([
            DateQuestion::make('start')->minDate('2026-01-01')->maxDate('2026-12-31'),
            ShortText::make('next'),
        ]))
            ->set('data.start', $date)
            ->advanceConversation('start');

        expect(errorFor($livewire, 'data.start'))->toBe($message);
    })->with([
        'valid' => ['2026-06-15', null],
        'no such day' => ['2026-02-31', 'Please enter a valid date.'],
        'too early' => ['2025-12-31', 'Please choose a date on or after 2026-01-01.'],
        'too late' => ['2027-01-01', 'Please choose a date on or before 2026-12-31.'],
    ]);

    it('formats answers in the chosen order', function () {
        expect(DateQuestion::make('d')->formatAnswer('2026-06-15'))->toBe('15/06/2026')
            ->and(DateQuestion::make('d')->format('MDY')->formatAnswer('2026-06-15'))->toBe('06/15/2026')
            ->and(DateQuestion::make('d')->formatAnswer(null))->toBe('');
    });

    it('works with date logic', function () {
        conversation(Conversation::make([
            DateQuestion::make('start')->logic([Logic::when('start', 'before', '2026-07-01')->jumpTo('soon')]),
            ShortText::make('later'),
            ShortText::make('soon'),
        ]))
            ->set('data.start', '2026-06-15')
            ->advanceConversation('start')
            ->assertConversationAdvancedTo('soon');
    });

    it('rejects unknown formats', function () {
        DateQuestion::make('d')->format('YDM')->getFormat();
    })->throws(InvalidArgumentException::class);
});

describe('TimeQuestion', function () {
    it('renders hour and minute boxes and validates the time', function () {
        $livewire = conversation(Conversation::make([TimeQuestion::make('time'), ShortText::make('next')]))
            ->assertSeeInOrder(['Hour', 'Minute'])
            ->assertDontSeeHtml('type="date"')
            ->set('data.time', '25:00')
            ->advanceConversation('time');

        expect(errorFor($livewire, 'data.time'))->toBe('Please enter a valid time.');

        $livewire->set('data.time', '09:30')->advanceConversation('time')->assertHasNoErrors();
    });
});

describe('ContactInfo and Address', function () {
    it('validates each part', function () {
        $livewire = conversation(Conversation::make([ContactInfo::make('contact')->required(), ShortText::make('next')]))
            ->assertSeeHtml('autocomplete="given-name"')
            ->assertSeeHtml('autocomplete="organization"')
            ->set('data.contact.email', 'not-an-email')
            ->advanceConversation('contact');

        $livewire->assertHasErrors(['data.contact.first_name' => 'required', 'data.contact.email' => 'email'])
            ->assertHasNoErrors(['data.contact.company']);

        expect(errorFor($livewire, 'data.contact.first_name'))->toBe('The first name field is required.');
    });

    it('only requires parts when the question is required', function () {
        conversation(Conversation::make([ContactInfo::make('contact'), ShortText::make('next')]))
            ->advanceConversation('contact')
            ->assertHasNoErrors();
    });

    it('asks only for the chosen parts', function () {
        $livewire = conversation(Conversation::make([
            ContactInfo::make('contact')->parts(['first_name', 'email'])->requiredParts(['email'])->required(),
        ]))
            ->assertDontSeeHtml('autocomplete="organization"')
            ->set('data.contact.email', 'jay@example.com');

        $livewire->submitConversation()->assertConversationCompleted();

        expect(submitted('contact'))->toBe(['first_name' => null, 'email' => 'jay@example.com']);
    });

    it('rejects unknown parts', function () {
        ContactInfo::make('contact')->parts(['nickname'])->getParts();
    })->throws(InvalidArgumentException::class, '[nickname] is not a part of');

    it('requires the essential parts of an address', function () {
        conversation(Conversation::make([Address::make('address')->required(), ShortText::make('next')]))
            ->assertSeeHtml('autocomplete="postal-code"')
            ->advanceConversation('address')
            ->assertHasErrors(['data.address.line1', 'data.address.city', 'data.address.postal_code', 'data.address.country'])
            ->assertHasNoErrors(['data.address.line2', 'data.address.state']);
    });

    it('formats answers', function () {
        expect(ContactInfo::make('c')->formatAnswer(['first_name' => 'Jay', 'last_name' => 'Ocero', 'email' => 'jay@example.com']))->toBe('Jay Ocero, jay@example.com')
            ->and(Address::make('a')->formatAnswer(['line1' => '1 Main St', 'city' => 'Manila', 'country' => 'Philippines']))->toBe('1 Main St, Manila, Philippines');
    });
});

describe('Matrix', function () {
    function satisfaction(): Matrix
    {
        return Matrix::make('satisfaction')
            ->rows(['speed' => 'Speed', 'support' => 'Support'])
            ->answers(['low' => 'Low', 'ok' => 'OK', 'high' => 'High']);
    }

    it('renders a radio group per row', function () {
        conversation(Conversation::make([satisfaction()]))
            ->assertSeeInOrder(['Speed', 'Support'])
            ->assertSeeHtml('type="radio"')
            ->assertSeeHtml('wire:model="data.satisfaction.speed"');
    });

    it('requires every row and only accepts its answers', function () {
        $livewire = conversation(Conversation::make([satisfaction()->required(), ShortText::make('next')]))
            ->set('data.satisfaction.speed', 'high')
            ->set('data.satisfaction.support', 'amazing')
            ->advanceConversation('satisfaction');

        $livewire->assertHasErrors(['data.satisfaction.support' => 'in'])->assertHasNoErrors(['data.satisfaction.speed']);

        $livewire->set('data.satisfaction.support', 'ok')->advanceConversation('satisfaction')->assertHasNoErrors();
    });

    it('allows several answers per row', function () {
        conversation(Conversation::make([satisfaction()->multiple()->required(), ShortText::make('next')]))
            ->assertSeeHtml('type="checkbox"')
            ->set('data.satisfaction.speed', ['low', 'high'])
            ->set('data.satisfaction.support', ['ok'])
            ->advanceConversation('satisfaction')
            ->assertHasNoErrors();
    });

    it('formats answers', function () {
        expect(satisfaction()->formatAnswer(['speed' => 'high', 'support' => 'ok']))->toBe('Speed: High; Support: OK');
    });
});

describe('Ranking', function () {
    it('starts in the given order', function () {
        conversation(Conversation::make([Ranking::make('priorities')->options(['price' => 'Price', 'speed' => 'Speed', 'support' => 'Support'])]))
            ->assertSet('data.priorities', ['price', 'speed', 'support'])
            ->assertSeeHtml('rankingQuestionComponent');
    });

    it('only accepts the options, each once', function (array $order, bool $isValid) {
        $livewire = conversation(Conversation::make([
            Ranking::make('priorities')->options(['price' => 'Price', 'speed' => 'Speed', 'support' => 'Support']),
            ShortText::make('next'),
        ]))
            ->set('data.priorities', $order)
            ->advanceConversation('priorities');

        $isValid ? $livewire->assertHasNoErrors() : $livewire->assertHasErrors(['data.priorities']);
    })->with([
        'reordered' => [['support', 'price', 'speed'], true],
        'missing an item' => [['support', 'price'], false],
        'duplicated item' => [['support', 'support', 'price'], false],
        'unknown item' => [['support', 'price', 'colour'], false],
    ]);

    it('formats answers', function () {
        expect(Ranking::make('p')->options(['a' => 'Alpha', 'b' => 'Beta'])->formatAnswer(['b', 'a']))->toBe('1. Beta, 2. Alpha');
    });
});

describe('FileUpload', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('renders a drop zone', function () {
        conversation(Conversation::make([FileUpload::make('cv')->acceptedFileTypes(['application/pdf'])->maxSize(2048)]))
            ->assertSeeHtml('type="file"')
            ->assertSeeHtml('accept="application/pdf"')
            ->assertSee(['Choose a file or drag it here', 'PDF · Up to 2 MB']);
    });

    it('describes accepted file types in words', function () {
        conversation(Conversation::make([FileUpload::make('logo')->image()->maxSize(512)]))
            ->assertSee('Images · Up to 512 KB');

        conversation(Conversation::make([FileUpload::make('doc')->acceptedFileTypes(['.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])]))
            ->assertSee('DOCX');
    });

    it('stores uploaded files with Filament', function () {
        $livewire = conversation(Conversation::make([FileUpload::make('cv')->disk('public')->directory('cvs')->required()]))
            ->set('data.cv.first', UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'));

        $livewire->submitConversation()->assertConversationCompleted();

        expect(submitted('cv'))->toStartWith('cvs/');
        Storage::disk('public')->assertExists(submitted('cv'));
    });

    it('validates files', function () {
        conversation(Conversation::make([
            FileUpload::make('cv')->disk('public')->acceptedFileTypes(['application/pdf'])->required(),
            ShortText::make('next'),
        ]))
            ->advanceConversation('cv')
            ->assertHasErrors(['data.cv' => 'required'])
            ->set('data.cv.first', UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload'))
            ->advanceConversation('cv')
            ->tap(fn ($livewire) => expect(errorFor($livewire, 'data.cv'))->toBe('The answer field must be a file of type: application/pdf.'));
    });
});

describe('Signature', function () {
    beforeEach(fn () => Storage::fake('local'));

    function pngDataUrl(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    it('renders a signature pad with a typed alternative', function () {
        conversation(Conversation::make([Signature::make('signature')]))
            ->assertSeeHtml('<canvas')
            ->assertSee(['Sign here', 'Type your name instead']);
    });

    it('stores a drawn signature as a PNG', function () {
        $livewire = conversation(Conversation::make([Signature::make('signature')->disk('local')->required()]))
            ->set('data.signature', pngDataUrl());

        $livewire->submitConversation()->assertConversationCompleted();

        expect(submitted('signature'))->toStartWith('signatures/')->toEndWith('.png');
        Storage::disk('local')->assertExists(submitted('signature'));
    });

    it('rejects anything that is not a PNG within the size limit', function (Closure $value) {
        $livewire = conversation(Conversation::make([Signature::make('signature')->disk('local')->maxSize(1), ShortText::make('next')]))
            ->set('data.signature', $value())
            ->advanceConversation('signature');

        expect(errorFor($livewire, 'data.signature'))->toBe('Please sign again.');
        Storage::disk('local')->assertDirectoryEmpty('signatures');
    })->with([
        'not an image' => [fn () => 'data:image/png;base64,' . base64_encode('<script>alert(1)</script>')],
        'another format' => [fn () => 'data:image/jpeg;base64,' . base64_encode('jpeg')],
        'not base64' => [fn () => 'data:image/png;base64,***'],
        'too large' => [fn () => 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('a', 4096))],
        'a path it did not store' => [fn () => '../../.env'],
    ]);

    it('keeps an existing signature when editing', function () {
        expect(Signature::make('signature')->store('signatures/01J.png'))->toBe('signatures/01J.png');
    });

    it('is required like any question', function () {
        conversation(Conversation::make([Signature::make('signature')->required(), ShortText::make('next')]))
            ->advanceConversation('signature')
            ->assertHasErrors(['data.signature' => 'required']);
    });
});
