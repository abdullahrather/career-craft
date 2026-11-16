<?php

declare(strict_types=1);

namespace App\JobSources;

use Carbon\CarbonImmutable;

/** One job as a source described it, already normalised. */
final readonly class FoundJob
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $url,
        public string $title,
        public string $company,
        public string $location,
        public string $workMode,
        public string $description,
        public ?CarbonImmutable $postedAt = null,
        public ?int $salaryMin = null,
        public ?int $salaryMax = null,
        public ?string $salaryCurrency = null,
        public ?string $salaryPeriod = null,
    ) {}
}
