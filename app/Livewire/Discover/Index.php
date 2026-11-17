<?php

declare(strict_types=1);

namespace App\Livewire\Discover;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\Jobs\ImportJobListings;
use App\JobSearch\ListingRanker;
use App\JobSources\Adzuna;
use App\Models\DismissedListing;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    /** How long a source isn't asked the same search again. */
    private const int FRESH_MINUTES = 60;

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

    /** The job just marked "not interested", so it can be brought back. */
    public ?int $hidden = null;

    /** A changed filter or order starts again at the first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['remote', 'sort', 'mode', 'salary'], true)) {
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
        $user = $this->user();

        foreach (ImportJobListings::SOURCES as $source) {
            if ($source === 'adzuna' && ! Adzuna::enabled()) {
                continue;
            }
            // A failed import gives the key back, so the search can be tried again.
            if (Cache::add(ImportJobListings::freshKey($source, $this->what, $this->where), true, now()->addMinutes(self::FRESH_MINUTES))) {
                Cache::put(ImportJobListings::runningKey($user->id, $source), true, now()->addMinutes(10));
                ImportJobListings::dispatch($source, $this->what, $this->where, $user->id);
            }
        }
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

        // The listing's own text when it has the whole post; otherwise its page is read.
        $text = trim(implode("\n", array_filter([$listing->title, implode(' · ', array_filter([$listing->company, $listing->location]))]))."\n\n".$listing->description);
        $post = mb_strlen($listing->description) >= 200
            ? $user->jobPosts()->create(['text' => $text, 'url' => $listing->sources->first()?->url])
            : $user->jobPosts()->create(['url' => $listing->sources->first()?->url]);
        AnalyseJobPost::dispatch($post);
        $this->redirectRoute('jobs.show', ['id' => $post->id]);
    }

    public function render(): View
    {
        $user = $this->user();
        $words = array_filter(preg_split('/\s+/u', $this->what) ?: []);
        $query = JobListing::query()
            ->where('last_seen_at', '>=', now()->subDays(JobListing::KEEP_DAYS))
            ->whereNotIn('id', $user->dismissedListings()->whereNotNull('job_listing_id')->select('job_listing_id'))
            ->when($words !== [], function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->where(fn (Builder $match) => $match->whereLike('title', "%{$word}%")->orWhereLike('company', "%{$word}%")->orWhereLike('description', "%{$word}%"));
                }
            })
            ->when($this->where !== '', fn (Builder $query) => $query->where(fn (Builder $place) => $place->whereLike('location', "%{$this->where}%")
                ->when($this->remote, fn (Builder $remote) => $remote->orWhere('work_mode', 'remote'))))
            ->when(in_array($this->mode, ['remote', 'hybrid', 'onsite'], true), fn (Builder $query) => $query->where('work_mode', $this->mode))
            ->when($this->salary, fn (Builder $query) => $query->where(fn (Builder $paid) => $paid->whereNotNull('salary_min')->orWhereNotNull('salary_max')));

        $ranker = new ListingRanker(
            $user->id,
            $user->profile()->with(['skills', 'experiences'])->first(),
            array_values($user->dismissedListings()->latest('id')->limit(DismissedListing::RANKED)->get(['title'])->map(fn (DismissedListing $dismissed): string => $dismissed->title)->all()),
        );
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

        return view('livewire.discover.index', [
            'listings' => $listings,
            'ranked' => $ranked,
            'canRank' => $ranker->canRank(),
            'yourSkills' => $ranked ? collect($listings->items())->mapWithKeys(fn (JobListing $listing): array => [$listing->id => $ranker->skillsIn($listing)])->all() : [],
            'hiddenTitle' => $this->hidden !== null ? $user->dismissedListings()->where('job_listing_id', $this->hidden)->value('title') : null,
            'fetching' => array_filter(ImportJobListings::SOURCES, fn (string $source): bool => Cache::has(ImportJobListings::runningKey($user->id, $source))) !== [],
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
