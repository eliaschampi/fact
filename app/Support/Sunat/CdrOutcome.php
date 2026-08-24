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
     * accepted                      code "0", no notes
     * accepted_with_observations    code "0" with notes, or numeric >= 4000
     * rejected                      CDR present and not accepted (typically 2000–3999)
     * unknown                       non-numeric code (PHP (int)"foo" === 0 is not acceptance)
     *
     * @param  list<string>  $notes
     */
    public static function fromCode(string $code, string $description, array $notes = []): self
    {
        if (! preg_match('/^\d+$/D', $code)) {
            return new self(CpeState::UNKNOWN, $code, $description, $notes);
        }

        $int = (int) $code;

        if ($code === '0' && $notes === []) {
            return new self(CpeState::ACCEPTED, $code, $description, $notes);
        }

        if ($code === '0' || $int >= 4000) {
            return new self(CpeState::ACCEPTED_WITH_OBSERVATIONS, $code, $description, $notes);
        }

        return new self(CpeState::REJECTED, $code, $description, $notes);
    }
}
