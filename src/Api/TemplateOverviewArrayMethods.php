<?php

namespace Sunnysideup\TemplateOverview\Api;

class TemplateOverviewArrayMethods
{
    public static function get_best_array_keys(?array $array): array
    {
        if (is_array($array)) {
            if (self::is_associative_array($array)) {
                $array = array_keys($array);
            }
        } else {
            $array = [];
        }

        return $array;
    }

    /**
     * isAssociativeArray
     *
     * @param array $arr
     * @return boolean
     */
    public static function is_associative_array(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
