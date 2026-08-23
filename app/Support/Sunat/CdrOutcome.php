<?php

namespace App\Support\Sunat;

use App\Support\Fiscal\CpeState;

final readonly class CdrOutcome
{
    public function __construct(
        public string $status,
        public string $code,
        public string $description,
        /** @var list<string> */
        public array $notes,
    ) {}

    /**
     * Map SUNAT CDR codes the way Greenter's CdrResponse::isAccepted does,
     * then split observations from a clean accept.
     *
     * accepted                      code 0, no notes
     * accepted_with_observations    code 0 with notes, or code >= 4000
     * rejected                      CDR present and not accepted (typically 2000–3999)
     *
     * @param  list<string>  $notes
     */
    public static function fromCode(string $code, string $description, array $notes = []): self
    {
        $int = (int) $code;

        if ($int === 0 && $notes === []) {
            return new self(CpeState::ACCEPTED, $code, $description, $notes);
        }

        if ($int === 0 || $int >= 4000) {
            return new self(CpeState::ACCEPTED_WITH_OBSERVATIONS, $code, $description, $notes);
        }

        return new self(CpeState::REJECTED, $code, $description, $notes);
    }
}
