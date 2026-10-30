<?php

namespace JaOcero\ConversationalForms\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Routing\Router;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversationPage;
use JaOcero\ConversationalForms\Tests\Fixtures\Models\User;
use JaOcero\ConversationalForms\Tests\Fixtures\Providers\AdminPanelProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        // Filament must boot before Livewire so that Filament's Livewire component
        // hooks (such as partial rendering) are attached, as in a real application.
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ConversationalFormsServiceProvider::class,
            AdminPanelProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        // CI also runs the suite against MySQL (`DB_CONNECTION=mysql`).
        $app['config']->set('database.default', env('DB_CONNECTION', 'testing'));
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $migrations = array_map(
            fn (string $name): object => include __DIR__ . "/../database/migrations/{$name}.php.stub",
            ['create_conversational_forms_tables', 'create_conversational_form_responses_table'],
        );

        foreach ($migrations as $migration) {
            $migration->up();
        }

        // In-memory SQLite starts empty for every test; a real database doesn't.
        $this->beforeApplicationDestroyed(function () use ($migrations): void {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
        });
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->livewire('/waitlist', TestConversationPage::class);
    }
}
