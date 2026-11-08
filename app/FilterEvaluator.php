<?php

namespace App;

class FilterEvaluator
{
    /** Tag-set fields; they take the has / !has operators with a tag name (case-insensitive). */
    public const TAG_FIELDS = ['tags', 'primary_tags'];

    public static function matches($project, $filter): bool
    {
        if (isset($filter['all']))
        {
            foreach ($filter['all'] as $rule)
            {
                if (!self::matches($project, $rule))
                {
                    return false;
                }
            }
        }

        if (isset($filter['any']))
        {
            // 👇 Treat empty "any" as always true
            if (empty($filter['any']))
            {
                return true;
            }

            foreach ($filter['any'] as $rule)
            {
                if (self::matches($project, $rule))
                {
                    return true;
                }
            }
            return false;
        }

        if (!isset($filter['field']))
        {
            return true; // a group with only "all", already checked above
        }

        return self::evaluateRule($project, $filter);
    }

    private static function evaluateRule($project, $rule): bool
    {
        $field = $rule['field'];
        $value = $rule['value'];
        $operator = $rule['operator'];

        if (in_array($field, self::TAG_FIELDS))
        {
            $names = self::tagNames($project, $field === 'primary_tags');
            $has = in_array(mb_strtolower((string) $value), $names, true);

            return match ($operator)
            {
                'has' => $has,
                '!has' => !$has,
                default => false,
            };
        }

        $actual = $project->{$field};
        $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;

        return match ($operator)
        {
            '=' => $actual == $value,
            '!=' => $actual != $value,
            default => false,
        };
    }

    /** Lower-cased tag names; primary = position 1-3. */
    private static function tagNames($project, bool $primaryOnly): array
    {
        return $project->tags
            ->filter(fn($tag) => !$primaryOnly || $tag->pivot->position !== null)
            ->map(fn($tag) => mb_strtolower($tag->name))
            ->values()
            ->all();
    }
}
