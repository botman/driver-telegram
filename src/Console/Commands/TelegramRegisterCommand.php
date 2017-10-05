<?php

namespace BotMan\Drivers\Telegram\Console\Commands;

use Illuminate\Console\Command;

class TelegramRegisterCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'botman:telegram:register {--output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register your bot with Telegram\'s webhook';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $url = 'https://api.telegram.org/bot'
                .config('botman.telegram.token')
                .'/setWebhook?url='
                .$this->ask('What is the target url for the telegram bot?');

        $this->info('Using '.$url);

        $this->info('Pinging Telegram...');

        $output = json_decode(file_get_contents($url));

        if ($output->ok == true && $output->result == true) {
            $this->info('Your bot is now set up with Telegram\'s webhook!');
        }

        if ($this->option('output')) {
            dump($output);
        }
    }
}
