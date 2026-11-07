<?php

declare(strict_types=1);

namespace App\Livewire\Resumes;

use App\Models\ResumeVersion;
use App\Models\User;
use App\Support\TextDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Two saved versions side by side as plain text, the way a parser reads them, with what
 * was added and taken out. Any two of the person's versions, from any of their CVs.
 */
final class Compare extends Component
{
    #[Url]
    public ?int $a = null;

    #[Url]
    public ?int $b = null;

    public function render(): View
    {
        $versions = $this->versions();
        $old = $this->a !== null ? $versions->find($this->a) : null;
        $new = $this->b !== null ? $versions->find($this->b) : null;

        return view('livewire.resumes.compare', [
            'versions' => $versions,
            'old' => $old,
            'new' => $new,
            'diff' => $old !== null && $new !== null ? TextDiff::lines($old->text, $new->text) : [],
        ]);
    }

    /** @return Collection<int, ResumeVersion> All the person's versions, newest first. */
    private function versions(): Collection
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return ResumeVersion::query()
            ->whereHas('resume', fn (Builder $query) => $query->where('user_id', $user->id))
            ->latest('id')
            ->get();
    }
}
