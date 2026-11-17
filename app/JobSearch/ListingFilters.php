<?php

declare(strict_types=1);

namespace App\JobSearch;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a person looks for on Find jobs: words, a place, a work mode, a salary and a company.
 * The page and the saved searches' email alerts find jobs the same way.
 */
final readonly class ListingFilters
{
    public const array MODES = ['remote', 'hybrid', 'onsite'];

    public function __construct(
        public string $what = '',
        public string $where = '',
        public bool $remote = true,
        public string $mode = '',
        public bool $salary = false,
        public string $company = '',
    ) {}

    /**
     * The person's jobs that match: still listed, and not marked "not interested".
     *
     * @return Builder<JobListing>
     */
    public function query(User $user): Builder
    {
        $words = array_filter(preg_split('/\s+/u', $this->what) ?: []);

        return JobListing::query()
            ->where('last_seen_at', '>=', now()->subDays(JobListing::KEEP_DAYS))
            ->whereNotIn('id', $user->dismissedListings()->whereNotNull('job_listing_id')->select('job_listing_id'))
            ->when($words !== [], function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->where(fn (Builder $match) => $match->whereLike('title', "%{$word}%")->orWhereLike('company', "%{$word}%")->orWhereLike('description', "%{$word}%"));
                }
            })
            ->when($this->where !== '', fn (Builder $query) => $query->where(fn (Builder $place) => $place->whereLike('location', "%{$this->where}%")
                ->when($this->remote, fn (Builder $remote) => $remote->orWhere('work_mode', 'remote'))))
            ->when(in_array($this->mode, self::MODES, true), fn (Builder $query) => $query->where('work_mode', $this->mode))
            ->when($this->salary, fn (Builder $query) => $query->where(fn (Builder $paid) => $paid->whereNotNull('salary_min')->orWhereNotNull('salary_max')))
            ->when($this->company !== '', fn (Builder $query) => $query->where('company', $this->company));
    }

    /** A short name for the search, such as "PHP · Berlin · Remote · with a salary". */
    public function label(): string
    {
        $modes = ['remote' => __('Remote'), 'hybrid' => __('Hybrid'), 'onsite' => __('On site')];

        return implode(' · ', array_filter([
            $this->what,
            match (true) {
                $this->company === '' => '',
                $this->what === '' => __('Jobs at :company', ['company' => $this->company]),
                default => __('at :company', ['company' => $this->company]),
            },
            $this->where,
            $modes[$this->mode] ?? '',
            $this->salary ? __('with a salary') : '',
        ])) ?: __('All jobs');
    }

    /**
     * The search as Find jobs page parameters.
     *
     * @return array<string, string|int>
     */
    public function parameters(): array
    {
        return array_filter([
            'what' => $this->what,
            'where' => $this->where,
            'remote' => $this->remote ? '' : 0,
            'mode' => $this->mode,
            'salary' => $this->salary ? 1 : '',
            'company' => $this->company,
        ], fn (string|int $value): bool => $value !== '');
    }
}
