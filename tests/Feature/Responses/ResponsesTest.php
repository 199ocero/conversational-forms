<?php

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use JaOcero\ConversationalForms\Analytics\FormAnalytics;
use JaOcero\ConversationalForms\Captcha\Turnstile;
use JaOcero\ConversationalForms\Enums\ResponseStatus;
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\EditConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ManageResponses;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\DropOffChart;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\NpsStats;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\ResponseStats;
use JaOcero\ConversationalForms\Livewire\ConversationalFormPage;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Support\ResponseExport;
use JaOcero\ConversationalForms\Support\ResponseTranscript;
use JaOcero\ConversationalForms\Tests\Fixtures\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/**
 * A published feedback form: a choice, a rating with a question that pipes
 * the choice, and an NPS question that routes detractors to their own ending.
 */
function feedbackForm(): ConversationalForm
{
    return ConversationalForm::factory()->withDefinition([
        'steps' => [
            ['type' => 'multiple_choice', 'key' => 'plan', 'label' => 'Which plan?', 'required' => true, 'settings' => ['options' => [
                ['value' => 'starter', 'label' => 'Starter'],
                ['value' => 'pro', 'label' => 'Pro'],
            ]]],
            ['type' => 'short_text', 'key' => 'reason', 'label' => 'Why {plan}?'],
            ['type' => 'nps', 'key' => 'recommend', 'label' => 'Recommend us?', 'required' => true, 'rules' => [
                ['if' => ['all' => [['field' => 'recommend', 'op' => 'lte', 'value' => 6]]], 'then' => [['add' => ['score', 1]], ['end_with' => 'sorry']]],
            ]],
        ],
        'variables' => ['score' => 0],
        'hidden_fields' => [['key' => 'ref', 'from_query' => 'ref']],
        'endings' => [['key' => 'default', 'title' => 'Thanks!'], ['key' => 'sorry', 'title' => 'Sorry to hear that']],
    ])->published()->create(['slug' => 'feedback']);
}

function answerFeedback(ConversationalForm $form, array $answers): Testable
{
    $page = Livewire::withQueryParams(['ref' => 'newsletter'])->test(ConversationalFormPage::class, ['slug' => $form->slug]);

    foreach ($answers as $key => $value) {
        $page->set("data.{$key}", $value)->advanceConversation($key);
    }

    return $page;
}

describe('storing responses', function () {
    it('saves a partial response after each step', function () {
        $form = feedbackForm();

        answerFeedback($form, ['plan' => 'pro']);

        $response = ConversationalFormResponse::query()->sole();

        expect($response->status)->toBe(ResponseStatus::Partial)
            ->and($response->steps)->toBe(['plan'])
            ->and($response->answers)->toMatchArray(['plan' => 'pro', 'ref' => 'newsletter'])
            ->and($response->conversational_form_version_id)->toBe($form->published_version_id);
    });

    it('updates the same response and completes it on submit', function () {
        $form = feedbackForm();
        $page = answerFeedback($form, ['plan' => 'pro', 'reason' => 'Speed']);

        $this->travel(65)->seconds();

        $page->set('data.recommend', 9)->submitConversation()->assertConversationCompleted('default');

        $response = ConversationalFormResponse::query()->sole();

        expect($response->status)->toBe(ResponseStatus::Completed)
            ->and($response->steps)->toBe(['plan', 'reason', 'recommend'])
            ->and(collect($response->answers)->sortKeys()->all())->toBe(['plan' => 'pro', 'reason' => 'Speed', 'recommend' => 9, 'ref' => 'newsletter'])
            ->and($response->ending)->toBe('default')
            ->and($response->variables)->toBe(['score' => 0])
            ->and($response->getDurationInSeconds())->toBe(65);
    });

    it('stores the ending and variables chosen by logic', function () {
        $page = answerFeedback(feedbackForm(), ['plan' => 'starter']);

        $this->travel(10)->seconds();

        $page->set('data.recommend', 3)->submitConversation()->assertConversationCompleted('sorry');

        expect(ConversationalFormResponse::query()->sole())
            ->ending->toBe('sorry')
            ->variables->toBe(['score' => 1]);
    });

    it('never stores previews', function () {
        Event::fake([ConversationalFormSubmitted::class]);

        $form = feedbackForm();
        $preview = new class extends ConversationalFormPage
        {
            protected function isPreviewRequest(Request $request): bool
            {
                return true;
            }
        };

        $page = Livewire::test($preview::class, ['slug' => $form->slug])
            ->assertSet('versionId', null)
            ->set('data.plan', 'pro')
            ->advanceConversation('plan')
            ->set('data.recommend', 9);

        $this->travel(30)->seconds();

        $page->submitConversation()->assertConversationCompleted('default');

        expect(ConversationalFormResponse::query()->count())->toBe(0);
        Event::assertDispatched(ConversationalFormSubmitted::class, fn (ConversationalFormSubmitted $event): bool => $event->isPreview() && ($event->response === null));
    });

    it('can skip partial responses', function () {
        config()->set('conversational-forms.responses.store_partials', false);

        answerFeedback(feedbackForm(), ['plan' => 'pro']);

        expect(ConversationalFormResponse::query()->count())->toBe(0);
    });

    it('prunes old partial responses and keeps completed ones', function () {
        $form = feedbackForm();
        $old = ConversationalFormResponse::query()->create(['conversational_form_id' => $form->id, 'status' => 'partial', 'answers' => [], 'started_at' => now()]);
        $recent = ConversationalFormResponse::query()->create(['conversational_form_id' => $form->id, 'status' => 'partial', 'answers' => [], 'started_at' => now()]);
        $completed = ConversationalFormResponse::query()->create(['conversational_form_id' => $form->id, 'status' => 'completed', 'answers' => [], 'started_at' => now(), 'completed_at' => now()]);
        ConversationalFormResponse::query()->whereKey([$old->id, $completed->id])->update(['updated_at' => now()->subDays(40)]);

        artisan('model:prune', ['--model' => ConversationalFormResponse::class])->assertSuccessful();

        expect(ConversationalFormResponse::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recent->id, $completed->id])->sort()->values()->all());
    });
});

describe('spam protection', function () {
    it('keeps nothing when the hidden field is filled in', function () {
        $page = answerFeedback(feedbackForm(), ['plan' => 'pro']);

        $this->travel(30)->seconds();

        $page->set('website', 'https://spam.example')
            ->set('data.recommend', 9)
            ->submitConversation()
            ->assertConversationCompleted('default');

        expect(ConversationalFormResponse::query()->count())->toBe(0);
    });

    it('keeps nothing submitted faster than a person could answer', function () {
        answerFeedback(feedbackForm(), ['plan' => 'pro'])
            ->set('data.recommend', 9)
            ->submitConversation();

        expect(ConversationalFormResponse::query()->count())->toBe(0);
    });

    it('limits how many requests one visitor makes', function () {
        config()->set('conversational-forms.spam.requests_per_minute', 3);

        $page = Livewire::test(ConversationalFormPage::class, ['slug' => feedbackForm()->slug]);

        $page->set('data.plan', 'pro');
        $page->set('data.plan', 'starter');

        $page->set('data.plan', 'pro')->assertStatus(429);
    });

    it('checks a Turnstile captcha before storing', function (bool $passes) {
        config()->set('conversational-forms.captcha.provider', 'turnstile');
        config()->set('conversational-forms.captcha.turnstile', ['site_key' => 'site', 'secret_key' => 'secret']);
        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => $passes])]);

        $page = answerFeedback(feedbackForm(), ['plan' => 'pro'])->assertSee('data-sitekey="site"', escape: false);

        $this->travel(30)->seconds();

        $page->set('captchaToken', 'token')->set('data.recommend', 9)->submitConversation();

        if ($passes) {
            $page->assertConversationCompleted('default')->assertHasNoErrors();
        } else {
            $page->assertHasErrors('captcha')->assertSet('completedConversationEnding', null);
        }

        expect(ConversationalFormResponse::query()->sole()->status)->toBe($passes ? ResponseStatus::Completed : ResponseStatus::Partial);
        Http::assertSent(fn ($request): bool => ($request['secret'] === 'secret') && ($request['response'] === 'token'));
    })->with(['passes' => true, 'fails' => false]);
});

describe('analytics', function () {
    it('counts starts, completions, time, drop-off, endings and NPS', function () {
        $form = feedbackForm();
        $make = fn (array $attributes) => ConversationalFormResponse::query()->create([
            'conversational_form_id' => $form->id,
            'started_at' => now(),
            ...$attributes,
        ]);

        $make(['status' => 'completed', 'steps' => ['plan', 'reason', 'recommend'], 'answers' => ['recommend' => 10], 'ending' => 'default', 'completed_at' => now()->addSeconds(60)]);
        $make(['status' => 'completed', 'steps' => ['plan', 'reason', 'recommend'], 'answers' => ['recommend' => 9], 'ending' => 'default', 'completed_at' => now()->addSeconds(120)]);
        $make(['status' => 'completed', 'steps' => ['plan', 'recommend'], 'answers' => ['recommend' => 7], 'ending' => 'default', 'completed_at' => now()->addSeconds(30)]);
        $make(['status' => 'completed', 'steps' => ['plan', 'recommend'], 'answers' => ['recommend' => 2], 'ending' => 'sorry', 'completed_at' => now()->addSeconds(30)]);
        $make(['status' => 'partial', 'steps' => ['plan', 'reason'], 'answers' => []]);
        $make(['status' => 'partial', 'steps' => ['plan'], 'answers' => []]);

        $analytics = FormAnalytics::for($form);

        expect($analytics->started)->toBe(6)
            ->and($analytics->completed)->toBe(4)
            ->and($analytics->completionRate())->toBe(66.7)
            ->and($analytics->averageSeconds)->toBe(60)
            ->and($analytics->answeredSteps)->toBe(['plan' => 6, 'reason' => 3, 'recommend' => 4])
            ->and($analytics->endings)->toBe(['default' => 3, 'sorry' => 1])
            ->and($analytics->nps)->toBe(['recommend' => ['promoters' => 2, 'passives' => 1, 'detractors' => 1]])
            ->and($analytics->npsScore('recommend'))->toBe(25);
    });

    it('has nothing to say without responses', function () {
        $analytics = FormAnalytics::for(feedbackForm());

        expect($analytics->completionRate())->toBeNull()
            ->and($analytics->averageSeconds)->toBeNull()
            ->and($analytics->npsScore('recommend'))->toBeNull();
    });
});

describe('the responses page', function () {
    beforeEach(function () {
        actingAs(User::query()->create(['name' => 'Jay', 'email' => 'jay@example.com', 'password' => 'secret']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    });

    function completedResponse(ConversationalForm $form, array $answers, array $attributes = []): ConversationalFormResponse
    {
        return ConversationalFormResponse::query()->create([
            'conversational_form_id' => $form->id,
            'conversational_form_version_id' => $form->published_version_id,
            'status' => 'completed',
            'answers' => $answers,
            'variables' => ['score' => 0],
            'steps' => array_keys($answers),
            'ending' => 'default',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            ...$attributes,
        ]);
    }

    it('lists completed responses with answers as people read them', function () {
        $form = feedbackForm();
        $completed = completedResponse($form, ['plan' => 'pro', 'reason' => 'Speed', 'recommend' => 10]);
        $partial = completedResponse($form, ['plan' => 'starter'], ['status' => 'partial', 'completed_at' => null]);

        Livewire::test(ManageResponses::class, ['record' => $form->getRouteKey()])
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$partial])
            ->assertSee('Pro')
            ->assertSee('Speed')
            ->filterTable('status', 'partial')
            ->assertCanSeeTableRecords([$partial]);
    });

    it('shows a transcript with the questions it was given', function () {
        $form = feedbackForm();
        $response = completedResponse($form, ['plan' => 'pro', 'reason' => 'Speed', 'recommend' => 10, 'ref' => 'newsletter']);

        expect(ResponseTranscript::for($response)->rows())->toBe([
            ['key' => 'plan', 'question' => 'Which plan?', 'answer' => 'Pro'],
            ['key' => 'reason', 'question' => 'Why Pro?', 'answer' => 'Speed'],
            ['key' => 'recommend', 'question' => 'Recommend us?', 'answer' => '10'],
            ['key' => 'ref', 'question' => 'ref', 'answer' => 'newsletter'],
        ]);

        Livewire::test(ManageResponses::class, ['record' => $form->getRouteKey()])
            ->mountTableAction('view', $response)
            ->assertMountedActionModalSee(['Why Pro?', 'Speed', 'newsletter']);
    });

    it('exports one row per response with readable answers', function () {
        $form = feedbackForm();
        completedResponse($form, ['plan' => 'pro', 'reason' => 'Speed, mostly', 'recommend' => 10, 'ref' => 'ad']);

        $rows = iterator_to_array((new ResponseExport($form, $form->responses()->getQuery()))->rows(), preserve_keys: false);

        expect($rows[0])->toBe(['Started', 'Completed', 'Status', 'Ending', 'Version', 'Which plan?', 'Why …?', 'Recommend us?', 'ref', '@score'])
            ->and(array_slice($rows[1], 2))->toBe(['Completed', 'default', 1, 'Pro', 'Speed, mostly', '10', 'ad', 0]);

        Livewire::test(ManageResponses::class, ['record' => $form->getRouteKey()])
            ->callTableAction('export_csv')
            ->assertFileDownloaded('feedback-responses-' . now()->format('Y-m-d') . '.csv');
    });

    it('shows the analytics widgets', function () {
        $form = feedbackForm();
        completedResponse($form, ['plan' => 'pro', 'recommend' => 10]);

        Livewire::test(ManageResponses::class, ['record' => $form->getRouteKey()])
            ->assertSeeLivewire(ResponseStats::class)
            ->assertSeeLivewire(NpsStats::class)
            ->assertSeeLivewire(DropOffChart::class);

        Livewire::test(NpsStats::class, ['record' => $form])
            ->assertSee('+100')
            ->assertSee('1 promoters · 0 passives · 0 detractors');
    });

    it('shares the link and embed snippets', function () {
        $form = feedbackForm();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->mountAction('share')
            ->assertMountedActionModalSee([url('/f/feedback'), '<div data-conversational-form="feedback"></div>', url('/f/embed.js')]);
    });
});

describe('embedding', function () {
    it('serves an embed script that only listens to this app', function () {
        get('/f/embed.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertSee('data-conversational-form', escape: false)
            ->assertSee('event.origin !== origin', escape: false)
            ->assertSee("const base = 'http:\\/\\/localhost\\/f'", escape: false);
    });

    it('shows the conversation for an iframe that reports its height', function () {
        feedbackForm();

        get('/f/feedback?embed=1')
            ->assertOk()
            ->assertSee('cf-embedded-body', escape: false)
            ->assertSee('conversational-forms:resize', escape: false);

        get('/f/feedback')->assertDontSee('conversational-forms:resize', escape: false);
    });
});
