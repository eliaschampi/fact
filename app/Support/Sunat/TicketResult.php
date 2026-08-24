<?php

namespace App\Support\Sunat;

final readonly class TicketResult
{
    public const string PENDING = 'pending';

    public const string COMPLETED = 'completed';

    public const string FAILED = 'failed';

    public const string ERROR = 'error';

    public function __construct(
        public string $status,
        public ?string $sunatCode,
        public ?string $cdrZip,
        public ?string $cdrCode,
        public ?string $cdrDescription,
        /** @var list<string> */
        public array $notes,
        public ?string $faultMessage,
    ) {}

    /**
     * Map SUNAT ticket statusCode / GRE codRespuesta.
     *
     * Greenter ExtService (SOAP RC/RA) and GreSender (GRE REST) both treat
     * 0 and 99 as "processing finished" (stop polling). They are not the
     * same business outcome:
     *
     * 98 → still running
     *  0 → finished OK at the ticket layer; inspect CDR
     * 99 → finished with errors (SOAP: "proceso con errores", GRE: "envío
     *      con error"). Do not treat as accepted. CDR may still be present.
     *
     * @param  list<string>  $notes
     */
    public static function fromSunatTicket(
        ?string $statusCode,
        ?string $cdrZip = null,
        ?string $cdrCode = null,
        ?string $cdrDescription = null,
        array $notes = [],
        ?string $faultMessage = null,
    ): self {
        $status = match ($statusCode) {
            null, '' => self::ERROR,
            '98' => self::PENDING,
            '0' => self::COMPLETED,
            '99' => self::FAILED,
            default => self::ERROR,
        };

        return new self(
            status: $status,
            sunatCode: $statusCode,
            cdrZip: $cdrZip,
            cdrCode: $cdrCode,
            cdrDescription: $cdrDescription,
            notes: $notes,
            faultMessage: $faultMessage,
        );
    }

    public static function transportError(?string $message): self
    {
        return new self(
            status: self::ERROR,
            sunatCode: null,
            cdrZip: null,
            cdrCode: null,
            cdrDescription: null,
            notes: [],
            faultMessage: $message,
        );
    }
}
