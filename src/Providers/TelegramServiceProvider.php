<?php

namespace BotMan\Drivers\Telegram\Providers;

use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;
use BotMan\Drivers\Telegram\TelegramFileDriver;
use BotMan\Drivers\Telegram\TelegramAudioDriver;
use BotMan\Drivers\Telegram\TelegramPhotoDriver;
use BotMan\Drivers\Telegram\TelegramVideoDriver;
use BotMan\Studio\Providers\StudioServiceProvider;
use BotMan\Drivers\Telegram\TelegramLocationDriver;
use BotMan\Drivers\Telegram\Console\Commands\TelegramRegisterCommand;

class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/telegram.php' => config_path('botman/telegram.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/telegram.php', 'botman.telegram');

            $this->commands([
                TelegramRegisterCommand::class,
            ]);
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(TelegramDriver::class);
        DriverManager::loadDriver(TelegramAudioDriver::class);
        DriverManager::loadDriver(TelegramFileDriver::class);
        DriverManager::loadDriver(TelegramLocationDriver::class);
        DriverManager::loadDriver(TelegramPhotoDriver::class);
        DriverManager::loadDriver(TelegramVideoDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
