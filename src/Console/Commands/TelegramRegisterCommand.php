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
    protected $signature = 'botman:telegram:register
                            {--r|remove : Remove the webhook endpoint}
                            {--o|output : Show the Telegrem\'s response}
                            {--url= : Telegram\'s webhook url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register or unregister your bot with Telegram\'s webhook';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $url = $this->getUrl();

        $this->info("Using {$url}");

        $this->info('Pinging Telegram...');

        $output = json_decode(file_get_contents($url));

        if ($output->ok == true && $output->result == true) {
            $this->info($this->successMsg($output));
        }

        if ($this->option('output')) {
            dump($output);
        }
    }

    /**
     *  Get the optional URL even as an url option or
     *  asking in console.
     *
     * @return url
     */
    protected function getUrl()
    {
        $url = 'https://api.telegram.org/bot'
                .config('botman.telegram.token')
                .'/setWebhook';

        if ($this->option('remove', null)) {
            return $url;
        }

        $urlOption = $this->option('url');
        if (empty($urlOption)) {
            return "{$url}?url=".$this->ask('What is the webhook url for the Telegram bot?');
        }

        return "{$url}?url={$urlOption}";
    }

    /**
     * return a success message depending on a
     * remove option.
     *
     * @return string
     */
    protected function successMsg($option)
    {
        return ($this->option('remove', null)
            ? 'Your bot Telegram\'s webhook has been removed!'
            : 'Your bot is now set up with Telegram\'s webhook!'
        ).' ('.$option->description.')';
    }
}
