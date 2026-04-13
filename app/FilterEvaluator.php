<?php

namespace App;

class FilterEvaluator
{
    public static function matches($project, $filter): bool
    {
        if (isset($filter['all']))
        {
            foreach ($filter['all'] as $rule)
            {
                if (!self::matches($project, $rule))
                    return false;
            }
            return true;
        }

        if (isset($filter['any']))
        {
            foreach ($filter['any'] as $rule)
            {
                if (self::matches($project, $rule))
                    return true;
            }
            return false;
        }

        return self::evaluateRule($project, $filter);
    }

    private static function evaluateRule($project, $rule): bool
    {
        $field = $rule['field'];
        $value = $rule['value'];
        $operator = $rule['operator'];

        return match ($operator)
        {
            '=' => $project->{$field} == $value,
            '!=' => $project->{$field} != $value,
            default => false,
        };
    }
}
