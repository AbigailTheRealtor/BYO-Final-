<?php

namespace App\Services\SmartTags;

final class ManualSelectionResult
{
    /**
     * @param string[]              $selected  the owner's manual tags after the write
     * @param string[]              $added
     * @param string[]              $removed
     * @param array<string, string> $rejected  requested value => reason
     */
    private function __construct(
        public readonly bool $saved,
        public readonly ?string $refusal,
        public readonly array $selected,
        public readonly array $added,
        public readonly array $removed,
        public readonly array $rejected,
        public readonly ?SmartTagResolution $resolution,
    ) {
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason, [], [], [], [], null);
    }

    /**
     * @param string[]              $selected
     * @param string[]              $added
     * @param string[]              $removed
     * @param array<string, string> $rejected
     */
    public static function saved(array $selected, array $added, array $removed, array $rejected, SmartTagResolution $resolution): self
    {
        return new self(true, null, $selected, $added, $removed, $rejected, $resolution);
    }
}
