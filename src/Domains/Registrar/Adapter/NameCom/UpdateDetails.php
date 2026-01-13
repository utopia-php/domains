<?php

namespace Utopia\Domains\Registrar\Adapter\NameCom;

use Utopia\Domains\Registrar\UpdateDetails as BaseUpdateDetails;

class UpdateDetails extends BaseUpdateDetails
{
    /**
     * @param bool|null $autorenewEnabled Enable or disable automatic renewal
     * @param bool|null $privacyEnabled Enable or disable WHOIS privacy
     * @param bool|null $locked Lock or unlock the domain
     */
    public function __construct(
        public ?bool $autorenewEnabled = null,
        public ?bool $privacyEnabled = null,
        public ?bool $locked = null,
    ) {
    }

    public function toArray(): array
    {
        $result = [];

        if ($this->autorenewEnabled !== null) {
            $result['autorenewEnabled'] = $this->autorenewEnabled;
        }

        if ($this->privacyEnabled !== null) {
            $result['privacyEnabled'] = $this->privacyEnabled;
        }

        if ($this->locked !== null) {
            $result['locked'] = $this->locked;
        }

        return $result;
    }
}
