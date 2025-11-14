<?php
function price2num($value, $rounding = 'MS')
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_string($value)) {
        $value = str_replace(array(' ', ','), array('', '.'), $value);
    }
    return (float) $value;
}

function price($value, $currency = '', $outputlangs = null, $conf = null, $rounding = 2)
{
    return number_format((float) $value, $rounding, '.', '');
}
