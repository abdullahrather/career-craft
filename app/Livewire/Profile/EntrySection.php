<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * A list section of the career profile, such as work experience: add, edit, delete and
 * reorder entries. Each section only describes its fields; everything else is here.
 *
 * Entries are always looked up through the signed-in person's own profile, so an ID
 * from someone else's profile simply isn't found.
 *
 * @template TEntry of Model
 */
abstract class EntrySection extends Component
{
    /** @var array<string, mixed> The entry being added or edited. */
    public array $form = [];

    /** The entry being edited, or null while adding. */
    public ?int $editing = null;

    public bool $open = false;

    /** @return HasMany<TEntry, Profile> */
    abstract protected function entries(Profile $profile): HasMany;

    /** @return array<string, mixed> An empty form. */
    abstract protected function blank(): array;

    /** @return array<string, mixed> Validation rules, keyed "form.field". */
    abstract protected function rules(): array;

    /** @return array<string, string> Field names for validation messages, keyed "form.field". */
    abstract protected function validationAttributes(): array;

    /** @return array<string, mixed> The form for an existing entry. */
    abstract protected function toForm(Model $entry): array;

    /**
     * @param  array<string, mixed>  $form  Validated input.
     * @return array<string, mixed> Columns to save.
     */
    abstract protected function toAttributes(array $form): array;

    /** @return view-string */
    abstract protected function view(): string;

    public function add(): void
    {
        $this->resetValidation();
        $this->form = $this->blank();
        $this->editing = null;
        $this->open = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->form = $this->toForm($this->find($id));
        $this->editing = $id;
        $this->open = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->editing = null;
        $this->form = [];
    }

    public function save(): void
    {
        /** @var array{form: array<string, mixed>} $validated */
        $validated = $this->validate();
        $attributes = $this->toAttributes($validated['form']);
        $profile = $this->profile();

        if ($this->editing !== null) {
            $this->find($this->editing)->update($attributes);
        } else {
            $last = $this->entries($profile)->max('position');
            $position = (is_numeric($last) ? (int) $last : 0) + 1;
            $this->entries($profile)->create([...$attributes, 'position' => $position]);
        }

        $this->cancel();
        $this->dispatch('profile-changed');
    }

    public function delete(int $id): void
    {
        $this->find($id)->delete();
        $this->cancel();
        $this->dispatch('profile-changed');
    }

    /** Swaps the entry with its neighbour above (-1) or below (+1). */
    public function move(int $id, int $direction): void
    {
        $entries = $this->entries($this->profile())->get()->values();
        $index = $entries->search(fn (Model $entry): bool => $entry->getKey() === $id);
        if ($index === false) {
            abort(404);
        }
        $neighbour = $entries->get($index + ($direction < 0 ? -1 : 1));
        if (! $neighbour instanceof Model) {
            return;
        }

        $entry = $entries->get($index);
        DB::transaction(function () use ($entries, $entry, $neighbour): void {
            // Renumber everything first, so positions are unique before swapping.
            $entries->each(fn (Model $item, int $number) => $item->update(['position' => $number]));
            $position = $entry?->getAttribute('position');
            $entry?->update(['position' => $neighbour->getAttribute('position')]);
            $neighbour->update(['position' => $position]);
        });
        $this->dispatch('profile-changed');
    }

    public function render(): View
    {
        return view($this->view(), ['entries' => $this->list()]);
    }

    /** @return Collection<int, TEntry> */
    protected function list(): Collection
    {
        return $this->entries($this->profile())->get();
    }

    protected function profile(): Profile
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->careerProfile();
    }

    protected function find(int $id): Model
    {
        return $this->entries($this->profile())->findOrFail($id);
    }

    protected static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** "2024-03" from a date, for a month field. */
    protected static function month(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m') : '';
    }

    /** The first day of a "2024-03" month, or null when empty. */
    protected static function date(mixed $month): ?string
    {
        return is_string($month) && $month !== '' ? $month.'-01' : null;
    }

    /** @return list<string> One entry per non-empty line. */
    protected static function lines(mixed $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', is_string($text) ? $text : '') ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    protected static function text(mixed $lines): string
    {
        return is_array($lines) ? implode("\n", array_filter($lines, 'is_string')) : '';
    }
}
