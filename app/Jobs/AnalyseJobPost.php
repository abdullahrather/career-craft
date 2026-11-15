<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\JobReader;
use App\Ai\Agents\Writer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\JobSearch\JobPage;
use App\JobSearch\Skills;
use App\JobSearch\UnreadablePage;
use App\Models\JobPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Reads a job post: fetches its page when it was linked, then has the AI pick out the
 * role and the skills. Only skills the post really names are kept, and its language is
 * worked out from the text, not asked of the AI.
 */
#[DeleteWhenMissingModels]
final class AnalyseJobPost implements ShouldQueue
{
    use Queueable;

    /** The most skills kept in each list. */
    private const int MAX_SKILLS = 25;

    public int $tries = 1;

    /** Room for the page and three tries at the longest AI timeout the admin can set. */
    public int $timeout = 600;

    public function __construct(public JobPost $post) {}

    public function handle(AiRunner $ai): void
    {
        try {
            if ($this->post->url !== null && $this->post->text === null) {
                $found = JobPage::fetch($this->post->url);
                // Saved straight away, so a try after an AI failure still has the page's own data.
                $this->post->update([
                    'text' => $found['text'],
                    'title' => Str::limit($found['title'], 250, ''),
                    'company' => Str::limit($found['company'], 250, ''),
                    'location' => Str::limit($found['location'], 250, ''),
                    'work_mode' => $found['remote'] ? 'remote' : '',
                ]);
            }
            $text = (string) $this->post->text;
            $response = $ai->prompt(new JobReader, JobReader::input($text), $this->post->user, 'job_analysis');
        } catch (UnreadablePage $exception) {
            $this->markFailed($exception->getMessage());

            return;
        } catch (AiLimitReached) {
            $this->markFailed('limit');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed('ai');

            return;
        }

        $answer = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
        $string = fn (string $key): string => is_string($answer[$key] ?? null) ? Str::limit(trim($answer[$key]), 250, '') : '';
        // The title, company and each part of the location only when the post says them.
        $said = fn (string $value): string => $value !== '' && Str::contains($text, $value, ignoreCase: true) ? $value : '';
        $location = implode(', ', array_filter(array_map(fn (string $part): string => $said(trim($part)), explode(',', $string('location')))));
        $required = self::skills($answer['required_skills'] ?? [], $text);
        $requiredKeys = array_map(Skills::key(...), $required);
        $nice = array_values(array_filter(self::skills($answer['nice_skills'] ?? [], $text), fn (string $skill): bool => ! in_array(Skills::key($skill), $requiredKeys, true)));
        $post = $this->post;
        $workMode = $post->work_mode !== '' ? $post->work_mode : $string('work_mode');
        $seniority = $string('seniority');

        $post->update([
            'status' => JobPost::READY,
            'error' => null,
            // The page's own data first, saved when it was fetched: it's what the employer
            // wrote, not what the AI read. The AI's answer only fills what's still empty.
            'title' => $post->title !== '' ? $post->title : $said($string('title')),
            'company' => $post->company !== '' ? $post->company : $said($string('company')),
            'location' => $post->location !== '' ? $post->location : $location,
            'work_mode' => in_array($workMode, JobPost::WORK_MODES, true) ? $workMode : '',
            'seniority' => in_array($seniority, JobPost::SENIORITIES, true) ? $seniority : '',
            'language' => Writer::isGerman($text) ? 'de' : 'en',
            'required_skills' => $required,
            'nice_skills' => $nice,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('ai');
    }

    /**
     * The skills the AI listed that the post really names, each once.
     *
     * @return list<string>
     */
    private static function skills(mixed $listed, string $text): array
    {
        $skills = [];
        foreach (is_array($listed) ? $listed : [] as $item) {
            // "Python and SQL" is two skills.
            $parts = is_string($item) ? preg_split('/\s*(?:,|;|\band\b|\bor\b|\bund\b|\boder\b)\s*/iu', $item) : [];
            foreach ($parts ?: [] as $skill) {
                $skill = trim($skill, " \t.:-");
                // A phrase of more than three words is a sentence from the post, not a skill.
                if ($skill === '' || str_word_count($skill) > 3 || isset($skills[Skills::key($skill)]) || ! Skills::in($skill, $text)) {
                    continue;
                }
                $skills[Skills::key($skill)] = $skill;
            }
        }

        return array_slice(array_values($skills), 0, self::MAX_SKILLS);
    }

    private function markFailed(string $reason): void
    {
        $this->post->update(['status' => JobPost::FAILED, 'error' => $reason]);
    }
}
