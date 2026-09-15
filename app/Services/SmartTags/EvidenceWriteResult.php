<?php

namespace App\Services\SmartTags;

final class EvidenceWriteResult
{
    /**
     * @param string[]              $written   tag keys persisted
     * @param array<string, string> $rejected  tag key => reason
     */
    public function __construct(
        public readonly array $written,
        public readonly array $rejected,
        public readonly ?SmartTagResolution $resolution = null,
    ) {
    }
}
