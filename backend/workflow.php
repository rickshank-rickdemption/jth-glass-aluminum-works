<?php

if (!function_exists('jthStatusLabels')) {
    function jthStatusLabels()
    {
        return [
            'Pending' => 'Pending Review',
            'Site Visit' => 'Site Visit',
            'Confirmed' => 'Confirmed',
            'Fabrication' => 'Fabrication',
            'Installation' => 'Installation',
            'Completed' => 'Completed',
            'Cancelled' => 'Cancelled',
            'Void' => 'Void'
        ];
    }
}

if (!function_exists('jthAllowedStatuses')) {
    function jthAllowedStatuses()
    {
        return array_keys(jthStatusLabels());
    }
}

if (!function_exists('jthWorkflowTransitions')) {
    function jthWorkflowTransitions()
    {
        return [
            'Pending' => ['Site Visit', 'Confirmed', 'Cancelled', 'Void'],
            'Site Visit' => ['Confirmed', 'Cancelled', 'Void'],
            'Confirmed' => ['Fabrication', 'Cancelled'],
            'Fabrication' => ['Installation', 'Cancelled'],
            'Installation' => ['Completed', 'Cancelled'],
            'Cancelled' => ['Pending'],
            'Completed' => [],
            'Void' => []
        ];
    }
}

if (!function_exists('jthTerminalStatuses')) {
    function jthTerminalStatuses()
    {
        return ['Completed', 'Void'];
    }
}

if (!function_exists('jthIsTerminalStatus')) {
    function jthIsTerminalStatus($status)
    {
        return in_array((string)$status, jthTerminalStatuses(), true);
    }
}

if (!function_exists('jthNormalizeIsoDateInput')) {
    function jthNormalizeIsoDateInput($value)
    {
        $raw = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return '';
        }

        $parts = explode('-', $raw);
        $y = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        $d = (int)($parts[2] ?? 0);
        if (!checkdate($m, $d, $y)) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}

if (!function_exists('jthNormalizeDateLoose')) {
    function jthNormalizeDateLoose($value)
    {
        $strict = jthNormalizeIsoDateInput($value);
        if ($strict !== '') {
            return $strict;
        }

        if (is_numeric($value)) {
            $n = (int)$value;
            if ($n > 0) {
                $ts = $n > 100000000000 ? (int)floor($n / 1000) : $n;
                return date('Y-m-d', $ts);
            }
        }

        $ts = strtotime((string)$value);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }
}
