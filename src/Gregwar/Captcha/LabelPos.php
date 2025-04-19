<?php

namespace Gregwar\Captcha;

class LabelPos {
    public $label;

    // Original rectangle corners
    public  $llx, $lly;
    public  $lrx, $lry;
    public  $urx, $ury;
    public  $ulx, $uly;

    // Center
    public  $cx, $cy;

    // Width and height
    public  $w, $h;

    // Square bounding box corners
    public  $sllx, $slly;
    public  $slrx, $slry;
    public  $sulx, $suly;
    public  $surx, $sury;

    // Square width and height
    public  $sw, $sh;

    // Square center
    public  $scx, $scy;

    public function __construct($label) {
        $this->label = $label;
    }
}
