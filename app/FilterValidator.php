<?php

namespace App;
class FilterValidator
{
    public static function validate($filter): bool
    {
        if (isset($filter['all']))
        {
            if (!is_array($filter['all']))
                return false;

            foreach ($filter['all'] as $rule)
            {
                if (!self::validate($rule))
                    return false;
            }

            return true;
        }

        if (isset($filter['any']))
        {
            if (!is_array($filter['any']))
                return false;

            foreach ($filter['any'] as $rule)
            {
                if (!self::validate($rule))
                    return false;
            }

            return true;
        }

        return self::validateRule($filter);
    }

    private static function validateRule($rule): bool
    {
        return isset($rule['field'], $rule['operator'], $rule['value']) &&
            in_array($rule['operator'], ['=', '!=']) &&
            in_array($rule['field'], [
                'type',
                'architect_approval',
                'community_vetted',
                'project_stage',
                'accessibility',
                'tier',
            ]);
    }
}
