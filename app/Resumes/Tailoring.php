<?php

declare(strict_types=1);

namespace App\Resumes;

use App\Jobs\TailorText;
use App\Models\Experience;
use App\Models\JobPost;
use App\Models\Resume;
use App\Models\TailoredChange;
use Illuminate\Support\Facades\DB;

/**
 * Starts a CV tailored to a job post: a copy of a CV the candidate chose, linked to the
 * post, with the AI asked to reword its summary and the bullet points of its latest jobs.
 */
final class Tailoring
{
    /** The latest jobs whose bullet points are reworded; older ones matter less for a new job. */
    private const int MAX_JOBS = 3;

    /** The first bullet points of each job that are reworded. */
    private const int MAX_BULLETS = 5;

    public static function start(Resume $base, JobPost $post): Resume
    {
        $resume = DB::transaction(function () use ($base, $post): Resume {
            $resume = $base->user->resumes()->make([
                'name' => mb_substr($base->name.' – '.($post->title !== '' ? $post->title : __('Job post')), 0, 120),
                'language' => $base->language,
                'template' => $base->template,
                'settings' => $base->settings,
            ]);
            $resume->job_post_id = $post->id;
            $resume->save();

            // Only what the CV shows: a hidden section isn't reworded.
            $position = 0;
            foreach (ResumeDocument::for($resume, tailored: false)->sections() as $section) {
                if ($section['key'] === 'summary' && is_string($section['content'])) {
                    $resume->changes()->create(['position' => $position++, 'original' => $section['content'], 'status' => TailoredChange::WAITING]);
                }
                if ($section['key'] === 'experiences' && ! is_string($section['content'])) {
                    foreach ($section['content']->take(self::MAX_JOBS) as $experience) {
                        if (! $experience instanceof Experience) {
                            continue;
                        }
                        foreach (array_slice(array_unique($experience->achievements), 0, self::MAX_BULLETS) as $line) {
                            $resume->changes()->create(['experience_id' => $experience->id, 'position' => $position++, 'original' => $line, 'status' => TailoredChange::WAITING]);
                        }
                    }
                }
            }

            return $resume;
        });

        foreach ($resume->changes as $change) {
            TailorText::dispatch($change);
        }

        return $resume;
    }
}
