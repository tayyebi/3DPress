<?php
namespace THREEDPRESS\Helpers;

class Estimate {
    /**
     * Calculate estimated cost and time for a 3D print order
     * @param float $length
     * @param float $width
     * @param float $height
     * @param float $scale
     * @param float $base_price
     * @param float $base_time
     * @return array [cost, time]
     */
    public static function calculate($length, $width, $height, $scale, $base_price, $base_time) {
        $volume = max($length * $width * $height, 1);
        $cost = round($base_price * $scale * ($volume/1000), 2);
        $time = round($base_time * $scale * ($volume/1000), 2);
        return [$cost, $time];
    }
}
