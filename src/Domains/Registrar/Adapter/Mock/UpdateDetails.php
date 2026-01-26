<?php

namespace Utopia\Domains\Registrar\Adapter\Mock;

use Utopia\Domains\Registrar\UpdateDetails as BaseUpdateDetails;
use Utopia\Domains\Registrar\Contact;

class UpdateDetails extends BaseUpdateDetails
{
    /**
     * @param array<string,mixed>|null $details Domain details to update (e.g., autoRenew, locked)
     * @param array<string,Contact>|Contact|null $contacts Contacts to update
     */
    public function __construct(
        public ?array $details = null,
        public array|Contact|null $contacts = null,
    ) {
    }

    public function toArray(): array
    {
        $result = [];

        if ($this->details !== null) {
            $result = array_merge($result, $this->details);
        }

        if ($this->contacts !== null) {
            $result['contacts'] = $this->contacts;
        }

        return $result;
    }
}
