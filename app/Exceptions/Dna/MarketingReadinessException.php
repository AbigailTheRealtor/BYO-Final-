<?php

namespace App\Exceptions\Dna;

use RuntimeException;

/**
 * MarketingReadinessException
 *
 * Thrown by AiMarketingReportGeneratorService when a PropertyDnaProfile fails
 * the Phase U marketing readiness gate (is_marketing_ready !== true).
 *
 * This exception aborts report generation before any prompt is assembled
 * or any call to OpenAiClientService is made. It carries the readiness snapshot
 * returned by PropertyMarketingReadinessService::build() so callers can inspect
 * which required information groups were missing at the time of the gate check.
 */
class MarketingReadinessException extends RuntimeException
{
    /**
     * The Phase U readiness snapshot captured at the gate check.
     *
     * @var array
     */
    private array $readinessSnapshot;

    /**
     * @param string $message          Human-readable explanation of the gate failure.
     * @param array  $readinessSnapshot The full Phase U output at the time of failure.
     */
    public function __construct(string $message, array $readinessSnapshot = [])
    {
        parent::__construct($message);
        $this->readinessSnapshot = $readinessSnapshot;
    }

    /**
     * Return the Phase U readiness snapshot that triggered this exception.
     *
     * @return array
     */
    public function getReadinessSnapshot(): array
    {
        return $this->readinessSnapshot;
    }
}
