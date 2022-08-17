<?php declare(strict_types=1);

namespace ArtisanCommandSpectatorTest;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * @inheritdoc
     */
    public function register(): void
    {
        $this->commands([
            Console\Commands\SpectatorTestcaseMakeCommand::class
        ]);
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/spectator-test.php' => config_path('spectator-test.php'),
        ], 'spectator-test');
    }

    /**
     * @inheritdoc
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            Console\Commands\SpectatorTestcaseMakeCommand::class
        ];
    }
}
