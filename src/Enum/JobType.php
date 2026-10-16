<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Enum;

enum JobType: string
{
    case VisionSingle = 'vision_single';
    case VisionBatch = 'vision_batch';
    case DocumentIngest = 'document_ingest';

    public function isVision(): bool
    {
        return $this === self::VisionSingle || $this === self::VisionBatch;
    }

    public function isSingleVision(): bool
    {
        return $this === self::VisionSingle;
    }

    public function isDocument(): bool
    {
        return $this === self::DocumentIngest;
    }

    public function isBatch(): bool
    {
        return $this === self::VisionBatch;
    }
}
