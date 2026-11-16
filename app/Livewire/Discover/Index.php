<?php

declare(strict_types=1);

namespace App\Livewire\Discover;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\Jobs\ImportJobListings;
use App\JobSources\Adzuna;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Jobs from public job sources. A search fetches new jobs in the background and shows the
 * ones already found straight away; the page refreshes while new ones come in. Any job can
 * be analysed like a pasted post, to match, tailor a CV and write a letter for it.
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
        $listings = JobListing::query()
            ->with('sources')
            ->where('last_seen_at', '>=', now()->subDays(JobListing::KEEP_DAYS))
            ->when($words !== [], function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->where(fn (Builder $match) => $match->whereLike('title', "%{$word}%")->orWhereLike('company', "%{$word}%")->orWhereLike('description', "%{$word}%"));
                }
            })
            ->when($this->where !== '', fn (Builder $query) => $query->where(fn (Builder $place) => $place->whereLike('location', "%{$this->where}%")
                ->when($this->remote, fn (Builder $remote) => $remote->orWhere('work_mode', 'remote'))))
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE);

        return view('livewire.discover.index', [
            'listings' => $listings,
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
