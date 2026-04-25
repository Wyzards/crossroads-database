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

        return self::evaluateRule($project, $filter);
    }

    private static function evaluateRule($project, $rule): bool
    {
        $field = $rule['field'];
        $value = $rule['value'];
        $operator = $rule['operator'];

        $actual = $project->{$field}->value;

        return match ($operator)
        {
            '=' => $actual == $value,
            '!=' => $actual != $value,
            default => false,
        };
    }
}
