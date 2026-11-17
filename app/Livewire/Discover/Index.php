<?php

declare(strict_types=1);

namespace App\Livewire\Discover;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Applications\ApplyQueue;
use App\Jobs\AnalyseJobPost;
use App\Jobs\ImportJobListings;
use App\JobSearch\ListingFilters;
use App\JobSearch\ListingRanker;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\JobListingSource;
use App\Models\JobPost;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Jobs from public job sources. A search fetches new jobs in the background and shows the
 * ones already found straight away; the page refreshes while new ones come in. Jobs come
 * best fit with the profile first, and a job marked "not interested" is hidden and ranks
 * similar ones lower. Any job can be analysed like a pasted post, to match, tailor a CV
 * and write a letter for it.
 */
final class Index extends Component
{
    use WithPagination;

    private const int PER_PAGE = 20;

    #[Url]
    public string $what = '';

    #[Url]
    public string $where = '';

    #[Url]
    public bool $remote = true;

    /** "match" for the best fit with the profile first, or "newest". */
    #[Url]
    public string $sort = 'match';

    /** One work mode only, or empty for any. */
    #[Url]
    public string $mode = '';

    /** Only jobs that give a salary. */
    #[Url]
    public bool $salary = false;

    /** One company's jobs only: its company page. Left out of the address when empty. */
    #[Url(except: '')]
    public string $company = '';

    /** How often a search saved now emails its new jobs. */
    public string $frequency = 'daily';

    /** The job just added to the apply queue, to say so. */
    public ?string $queued = null;

    /** The job just marked "not interested", so it can be brought back. */
    public ?int $hidden = null;

    /** A changed filter or order starts again at the first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['remote', 'sort', 'mode', 'salary', 'company'], true)) {
            $this->resetPage();
        }
    }

    /** Hides a job from this person, and ranks jobs with similar titles lower. */
    public function dismiss(int $listingId): void
    {
        $listing = JobListing::query()->findOrFail($listingId);
        $this->user()->dismissedListings()->firstOrCreate(['job_listing_id' => $listing->id], ['title' => $listing->title]);
        $this->hidden = $listing->id;
    }

    /** Brings back the job just hidden. */
    public function undo(): void
    {
        if ($this->hidden !== null) {
            $this->user()->dismissedListings()->where('job_listing_id', $this->hidden)->delete();
        }
        $this->hidden = null;
    }

    /** Fetches new jobs for the search from every source, unless a source was asked lately. */
    public function search(): void
    {
        $this->what = trim($this->what);
        $this->where = trim($this->where);
        $this->validate(['what' => ['nullable', 'string', 'max:100'], 'where' => ['nullable', 'string', 'max:100']], attributes: ['what' => __('What'), 'where' => __('Where')]);
        $this->resetPage();
        ImportJobListings::fetch($this->what, $this->where, $this->user()->id);
    }

    /** Saves the search on the page, to email its new jobs daily or weekly. */
    public function saveSearch(): void
    {
        $this->validate([
            'frequency' => ['required', Rule::in(SavedSearch::FREQUENCIES)],
            'what' => ['nullable', 'string', 'max:100'],
            'where' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', Rule::in(ListingFilters::MODES)],
            'company' => ['nullable', 'string', 'max:250'],
        ], attributes: ['frequency' => __('Email me'), 'what' => __('What'), 'where' => __('Where'), 'mode' => __('Work mode'), 'company' => __('Company')]);
        $user = $this->user();
        $search = $this->filters();
        $values = ['what' => trim($search->what), 'where' => trim($search->where), 'remote' => $search->remote, 'mode' => $search->mode, 'salary' => $search->salary, 'company' => $search->company];
        $saved = $user->savedSearches()->where($values)->first();
        if ($saved === null && $user->savedSearches()->count() >= SavedSearch::MAX) {
            $this->addError('frequency', __('You can save up to :max searches. Delete one first.', ['max' => SavedSearch::MAX]));

            return;
        }
        // Only jobs found from now on are new to its first alert.
        $saved === null
            ? $user->savedSearches()->create([...$values, 'frequency' => $this->frequency, 'checked_at' => now()])
            : $saved->update(['frequency' => $this->frequency]);
        session()->flash('saved', __('Search saved. New jobs for it will be emailed to you.'));
    }

    /** Runs a saved search: its filters on the page, and new jobs fetched for it. */
    public function openSearch(int $id): void
    {
        $saved = $this->user()->savedSearches()->findOrFail($id);
        $this->fill(['what' => $saved->what, 'where' => $saved->where, 'remote' => $saved->remote, 'mode' => $saved->mode, 'salary' => $saved->salary, 'company' => $saved->company]);
        $this->search();
    }

    public function setFrequency(int $id, string $frequency): void
    {
        abort_unless(in_array($frequency, SavedSearch::FREQUENCIES, true), 422);
        $this->user()->savedSearches()->findOrFail($id)->update(['frequency' => $frequency]);
    }

    public function deleteSearch(int $id): void
    {
        $this->user()->savedSearches()->whereKey($id)->delete();
    }

    /** Shows the jobs of a company just followed. */
    #[On('jobs-imported')]
    public function jobsImported(): void
    {
        $this->resetPage();
    }

    /** Analyses a job like a pasted post, then opens it. */
    public function analyse(AiRunner $ai, int $listingId): void
    {
        $listing = JobListing::query()->with('sources')->findOrFail($listingId);
        $user = $this->user();
        if ($ai->remaining($user) === 0) {
            $this->addError('analyse', (new AiLimitReached)->getMessage());

            return;
        }

        $post = JobPost::fromListing($user, $listing);
        AnalyseJobPost::dispatch($post);
        $this->redirectRoute('jobs.show', ['id' => $post->id]);
    }

    /** Adds a job to the person's applications, in Saved, unless it is there already. */
    public function track(int $listingId): void
    {
        $listing = JobListing::query()->with('sources')->findOrFail($listingId);
        $source = $listing->sources->first();
        $user = $this->user();
        if ($source !== null && $user->applications()->where('url', $source->url)->exists()) {
            return;
        }
        Application::track($user, [
            'title' => $listing->title,
            'company' => $listing->company,
            'location' => $listing->location,
            'url' => $source?->url,
            'source' => $source !== null ? (JobListingSource::NAMES[$source->source] ?? $source->source) : '',
        ]);
    }

    /** Adds a job to the apply queue, to be prepared for review. */
    public function queue(ApplyQueue $queue, int $listingId): void
    {
        $result = $queue->add($this->user(), JobListing::query()->with('sources')->findOrFail($listingId));
        if (is_string($result)) {
            $this->addError('queue', $result);

            return;
        }
        $this->queued = $result->title;
    }

    public function render(): View
    {
        $user = $this->user();
        $query = $this->filters()->query($user);
        $ranker = ListingRanker::for($user);
        $ranked = $this->sort === 'match' && $ranker->canRank();
        if ($ranked) {
            // Every matching job is ranked; then the page's jobs are loaded, in that order.
            $page = is_numeric($this->getPage()) ? max(1, (int) $this->getPage()) : 1;
            $ids = array_slice($ranker->rank($query->get(['id', 'title', 'posted_at', 'updated_at'])), ($page - 1) * self::PER_PAGE, self::PER_PAGE + 1);
            $items = JobListing::query()->with('sources')->whereKey($ids)->get()->sortBy(fn (JobListing $listing): int|false => array_search($listing->id, $ids, true))->values();
            $listings = new Paginator($items, self::PER_PAGE, $page);
        } else {
            $listings = $query->with('sources')->orderByDesc('posted_at')->orderByDesc('id')->simplePaginate(self::PER_PAGE);
        }
        // A job is in the person's applications when one of its links is.
        $links = collect($listings->items())->flatMap(fn (JobListing $listing) => $listing->sources->pluck('url'))->all();
        $trackedLinks = $links !== [] ? $user->applications()->whereIn('url', $links)->pluck('url')->all() : [];

        return view('livewire.discover.index', [
            'listings' => $listings,
            'ranked' => $ranked,
            'canRank' => $ranker->canRank(),
            'yourSkills' => $ranked ? collect($listings->items())->mapWithKeys(fn (JobListing $listing): array => [$listing->id => $ranker->skillsIn($listing)])->all() : [],
            'inQueue' => $user->applyQueueItems()->whereNull('skipped_at')->whereIn('job_listing_id', collect($listings->items())->map(fn (JobListing $listing): int => $listing->id)->all())->pluck('job_listing_id')->all(),
            'tracked' => array_values(collect($listings->items())->filter(fn (JobListing $listing): bool => $listing->sources->pluck('url')->intersect($trackedLinks)->isNotEmpty())->map(fn (JobListing $listing): int => $listing->id)->all()),
            'hiddenTitle' => $this->hidden !== null ? $user->dismissedListings()->where('job_listing_id', $this->hidden)->value('title') : null,
            'companyFacts' => $this->company !== '' ? $this->companyFacts($user) : null,
            'savedSearches' => $user->savedSearches()->get(),
            'fetching' => array_filter(ImportJobListings::SOURCES, fn (string $source): bool => Cache::has(ImportJobListings::runningKey($user->id, $source))) !== [],
        ]);
    }

    private function filters(): ListingFilters
    {
        return new ListingFilters($this->what, $this->where, $this->remote, $this->mode, $this->salary, $this->company);
    }

    /**
     * What the company page says about a company: its open jobs, where, how, and whether
     * the person follows its job board.
     *
     * @return array{jobs: int, places: list<string>, modes: array<string, int>, followed: bool}
     */
    private function companyFacts(User $user): array
    {
        $listings = JobListing::query()->where('company', $this->company)->where('last_seen_at', '>=', now()->subDays(JobListing::KEEP_DAYS))->get(['location', 'work_mode']);

        return [
            'jobs' => $listings->count(),
            'places' => array_values($listings->map(fn (JobListing $listing): string => $listing->location)->filter()->countBy()->sortDesc()->keys()->take(5)->map(fn (int|string $place): string => (string) $place)->all()),
            'modes' => array_count_values(array_values(array_filter($listings->map(fn (JobListing $listing): string => $listing->work_mode)->all()))),
            'followed' => $user->followedBoards()->where('name', $this->company)->exists(),
        ];
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
