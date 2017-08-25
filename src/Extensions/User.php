<?php
namespace BotMan\Drivers\Telegram\Extensions;

use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Users\User as BotManUser;

class User extends BotManUser implements UserInterface
{

    /**
     * The member's status in the chat.
     * Can be “creator”, “administrator”, “member”, “restricted”, “left” or “kicked”
     *
     * @return string
     */
    public function getStatus() : ?string
    {
        $info = $this->getInfo();
        return $info['status'] ?? null;
    }

    /**
     * IETF language tag of the user's language.
     *
     * @return string
     */
    public function getLanguageCode() : ?string
    {
        $info = $this->getInfo();
        return $info['user']['language_code'] ?? null;
    }

}