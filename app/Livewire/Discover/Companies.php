<?php

declare(strict_types=1);

namespace App\Livewire\Discover;

use App\JobSources\CompanyBoards;
use App\JobSources\ListingStore;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The company job boards a person follows. Following one fetches its jobs straight away,
 * which also shows the link works; after that, they're fetched again every day.
 */
final class Companies extends Component
{
    public string $link = '';

    public function follow(): void
    {
        $this->link = trim($this->link);
        $this->validate(['link' => ['required', 'string', 'max:500']], attributes: ['link' => __('Job board link')]);
        $found = CompanyBoards::parse($this->link);
        if ($found === null) {
            $this->addError('link', __('Paste a link to a company\'s job board on Greenhouse, Lever or Ashby.'));

            return;
        }
        $user = $this->user();
        if ($user->followedBoards()->where($found)->exists()) {
            $this->addError('link', __('You already follow this company.'));

            return;
        }

        try {
            $jobs = CompanyBoards::jobs($found['board'], $found['slug']);
        } catch (RequestException $exception) {
            $this->addError('link', $exception->response->notFound()
                ? __('There is no job board at this link. Check the company name in it.')
                : __('The job board didn\'t answer. Try again later.'));

            return;
        } catch (ConnectionException) {
            $this->addError('link', __('The job board didn\'t answer. Try again later.'));

            return;
        }

        ListingStore::save($jobs);
        $user->followedBoards()->create([...$found, 'name' => ($jobs[0] ?? null)?->company ?: Str::headline($found['slug'])]);
        $this->reset('link');
        $this->dispatch('jobs-imported');
    }

    public function unfollow(int $id): void
    {
        $this->user()->followedBoards()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.discover.companies', ['boards' => $this->user()->followedBoards()->get()]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
