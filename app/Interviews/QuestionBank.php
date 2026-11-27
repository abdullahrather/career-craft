<?php

declare(strict_types=1);

namespace App\Interviews;

use App\Models\Application;
use App\Models\JobPost;

/**
 * Questions interviewers often ask, picked for an application: behavioural ones for
 * everyone, then by the kind of role its title names, by its seniority, and about the
 * company. They come in the job post's language, as the interview most likely will.
 */
final class QuestionBank
{
    /**
     * Kinds of role, by words in the job title. The first that matches wins, so a "Product
     * Designer" is a designer and a "Sales Engineer" in sales.
     */
    private const array ROLES = [
        'design' => '/(?<!\pL)(design|ux|ui|gestalt)/iu',
        'data' => '/(?<!\pL)(data|datenanaly|analyst|analytics|scientist|machine learning|business intelligence)/iu',
        'product' => '/(?<!\pL)(product manager|product owner|produktmanager|project manager|projektmanager|projektleit|scrum master)/iu',
        'sales' => '/(?<!\pL)(sales|vertrieb|account manager|account executive|marketing|business development)/iu',
        'software' => '/(?<!\pL)(developer|engineer|entwickler|programmier|devops|software|informatiker|architect)/iu',
    ];

    /** Seniority by words in the title, for an application without an analysed post. */
    private const array SENIORITIES = [
        'lead' => '/(?<!\pL)(lead|head of|principal|teamleit)/iu',
        'senior' => '/(?<!\pL)senior/iu',
        'junior' => '/(?<!\pL)junior/iu',
        'intern' => '/(?<!\pL)(werkstudent|praktik|internship|intern(?!\pL))/iu',
    ];

    /** The language the questions are in: the job post's, or else the page's. */
    public static function locale(Application $application): string
    {
        $post = $application->jobPost;

        return $post !== null && $post->status === JobPost::READY ? $post->language : app()->getLocale();
    }

    /**
     * The questions by group, in the order shown: behavioural, role, level, company.
     * Groups without questions are left out.
     *
     * @return array<string, list<string>>
     */
    public static function for(Application $application): array
    {
        $locale = self::locale($application);
        $post = $application->jobPost;
        $seniority = $post !== null && $post->status === JobPost::READY ? $post->seniority : '';
        if ($seniority === '') {
            $seniority = self::match(self::SENIORITIES, $application->title);
        }

        return array_filter([
            'behavioural' => self::behavioural($locale),
            'role' => self::role(self::match(self::ROLES, $application->title), $locale),
            'level' => self::level($seniority, $locale),
            'company' => self::company((string) $application->company, $locale),
        ]);
    }

    /** @param  array<string, string>  $patterns */
    private static function match(array $patterns, string $title): string
    {
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return $key;
            }
        }

        return '';
    }

    /** @return list<string> */
    private static function behavioural(string $locale): array
    {
        return [
            __('Tell me about yourself.', [], $locale),
            __('Tell me about a time you solved a difficult problem at work.', [], $locale),
            __('Describe a situation where you disagreed with a colleague. What did you do?', [], $locale),
            __('Tell me about a mistake you made and what you learned from it.', [], $locale),
            __('Give an example of a time you had several deadlines at once. How did you prioritise?', [], $locale),
            __('What are you most proud of in your last role?', [], $locale),
        ];
    }

    /** @return list<string> */
    private static function role(string $role, string $locale): array
    {
        return match ($role) {
            'software' => [
                __('How do you make sure your code stays easy to change?', [], $locale),
                __('Walk me through a technical decision you made and its trade-offs.', [], $locale),
                __('How do you find the cause of a bug you can\'t reproduce?', [], $locale),
                __('How do you review a colleague\'s code?', [], $locale),
            ],
            'data' => [
                __('How do you check that data is reliable before you use it?', [], $locale),
                __('Tell me about an analysis that changed a decision.', [], $locale),
                __('How do you explain a result to people without a technical background?', [], $locale),
                __('Which metric would you use to measure whether a feature works, and why?', [], $locale),
            ],
            'design' => [
                __('Walk me through a project from research to the final design.', [], $locale),
                __('How do you handle feedback on your design that you disagree with?', [], $locale),
                __('How do you decide what to test with users?', [], $locale),
                __('How do you work with developers so a design is built as intended?', [], $locale),
            ],
            'product' => [
                __('How do you decide what goes into the next release?', [], $locale),
                __('Tell me about a time you had to say no to a stakeholder.', [], $locale),
                __('How do you know whether something you shipped worked?', [], $locale),
                __('How do you keep a project on track when the requirements change?', [], $locale),
            ],
            'sales' => [
                __('How do you prepare for a first meeting with a new customer?', [], $locale),
                __('Tell me about a deal or campaign that didn\'t work out, and what you learned.', [], $locale),
                __('How do you respond when a customer says the price is too high?', [], $locale),
                __('How do you decide which leads or channels to focus on?', [], $locale),
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private static function level(string $seniority, string $locale): array
    {
        return match ($seniority) {
            'intern', 'junior' => [
                __('What have you learned recently, and how did you learn it?', [], $locale),
                __('What do you do when you are stuck on a task?', [], $locale),
            ],
            'mid' => [
                __('Tell me about a piece of work you owned from start to finish.', [], $locale),
                __('How do you help newer colleagues get started?', [], $locale),
            ],
            'senior' => [
                __('Tell me about a time you changed how your team works.', [], $locale),
                __('How do you weigh speed against quality?', [], $locale),
            ],
            'lead' => [
                __('How do you give feedback to someone whose work isn\'t good enough?', [], $locale),
                __('How do you build trust in a team you have just joined?', [], $locale),
                __('How do you decide what to delegate?', [], $locale),
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private static function company(string $company, string $locale): array
    {
        return [
            ...($company !== '' ? [
                __('Why do you want to work at :company?', ['company' => $company], $locale),
                __('What would you like to achieve in your first six months at :company?', ['company' => $company], $locale),
            ] : []),
            __('Why are you a good fit for this role?', [], $locale),
            __('What questions do you have for us?', [], $locale),
        ];
    }
}
