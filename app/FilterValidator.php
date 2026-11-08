<?php

namespace App;
class FilterValidator
{
    public static function validate($filter): bool
    {
        if (!is_array($filter))
            return false;

        if (!isset($filter['all']) && !isset($filter['any']))
            return self::validateRule($filter);

        // A group: validate both halves (previously "any" went unchecked when "all" was present).
        foreach (['all', 'any'] as $key)
        {
            if (!isset($filter[$key]))
                continue;
            if (!is_array($filter[$key]))
                return false;
            foreach ($filter[$key] as $rule)
                if (!self::validate($rule))
                    return false;
        }

        return true;
    }

    private static function validateRule($rule): bool
    {
        if (!isset($rule['field'], $rule['operator'], $rule['value']))
            return false;

        // Tag-set fields compare by membership; the others by value.
        if (in_array($rule['field'], FilterEvaluator::TAG_FIELDS))
            return in_array($rule['operator'], ['has', '!has']);

        return in_array($rule['operator'], ['=', '!=']) &&
            in_array($rule['field'], [
                'format',
                'architect_approval',
                'community_vetted',
                'project_stage',
                'accessibility',
                'tier',
            ]);
    }
}
