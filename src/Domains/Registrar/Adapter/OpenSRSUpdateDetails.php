<?php

namespace Utopia\Domains\Registrar\Adapter;

use Utopia\Domains\Registrar\UpdateDetails;
use Utopia\Domains\Registrar\Contact;

class OpenSRSUpdateDetails extends UpdateDetails
{
    /**
     * @param string $data The data type to update (e.g., 'contact_info', 'ca_whois_display_setting')
     * @param array<string,Contact>|null $contacts Associative array of contacts by type (owner, admin, tech, billing)
     * @param string|null $display Display setting for CA domains (e.g., 'FULL', 'PRIVATE')
     * @param array<string,mixed> $additionalData Additional data for specific update types
     */
    public function __construct(
        public string $data,
        public ?array $contacts = null,
        public ?string $display = null,
        public array $additionalData = [],
    ) {
    }

    public function toArray(): array
    {
        $result = [
            'data' => $this->data,
        ];

        if ($this->display !== null) {
            $result['display'] = $this->display;
        }

        // Merge any additional data
        if (!empty($this->additionalData)) {
            $result = array_merge($result, $this->additionalData);
        }

        return $result;
    }
}
