<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * What application forms ask besides the CV: salary expectation, notice period, earliest start
 * and work permit. Entered once, shown ready to copy for each queued job, never on a CV.
 */
final class ApplicationDetails extends Component
{
    public string $salaryExpectation = '';

    public string $noticePeriod = '';

    public string $earliestStart = '';

    public string $workPermit = '';

    public bool $saved = false;

    public function mount(): void
    {
        $profile = $this->profile();
        $this->salaryExpectation = (string) $profile->salary_expectation;
        $this->noticePeriod = $profile->notice_period;
        $this->earliestStart = (string) $profile->earliest_start?->toDateString();
        $this->workPermit = $profile->work_permit;
    }

    public function save(): void
    {
        $this->validate([
            'salaryExpectation' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'noticePeriod' => ['nullable', 'string', 'max:100'],
            'earliestStart' => ['nullable', 'date_format:Y-m-d'],
            'workPermit' => ['nullable', 'string', 'max:200'],
        ], attributes: ['salaryExpectation' => __('Salary expectation'), 'noticePeriod' => __('Notice period'), 'earliestStart' => __('Earliest start'), 'workPermit' => __('Work permit')]);

        $this->profile()->update([
            'salary_expectation' => $this->salaryExpectation !== '' ? (int) $this->salaryExpectation : null,
            'notice_period' => trim($this->noticePeriod),
            'earliest_start' => $this->earliestStart !== '' ? $this->earliestStart : null,
            'work_permit' => trim($this->workPermit),
        ]);
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.profile.application-details');
    }

    private function profile(): Profile
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->careerProfile();
    }
}
