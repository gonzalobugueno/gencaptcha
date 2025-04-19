<?php

namespace Gregwar\Captcha;

use \Exception;
use \InvalidArgumentException;
use \RuntimeException;

class AnnotatedCaptchaBuilder extends CaptchaBuilder
{
    private $width;
    private $height;
    private $counter = 0;
    private $labels = [];

    public $chartoidx;

    public function __construct() {
        parent::__construct(null, null);

        $this->chars = str_split($this->builder->charset);
        $this->chartoidx = [];
        foreach ($this->chars as $index => $char) {
            $this->chartoidx[$char] = $index;
        }
    }

    private function checkStructure($dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true))
                throw new Exception("Unable to create directory structure: $dir");
        }
    }


    public function saveYaml($destdir = 'dataset') {
        // Build the names map with ASCII ord values as keys
        $names = [];
        $chars = str_split($this->builder->charset);
        foreach ($this->chars as $char) {
            $id = $this->chartoidx[$char];
            $names[$id] = $char;
        }

        // Sort by ID to keep the output clean (optional)
        ksort($names);

        // YAML content

        $yaml = "path: " . getcwd() . "\n";
        $yaml .= "train: $destdir/images/train\n";
        $yaml .= "val: $destdir/images/val\n";
        $yaml .= "test: $destdir/images/test\n";
        $yaml .= "nc: " . count($names) . "\n";
        $yaml .= "names:\n";

        foreach ($names as $id => $char) {
            $yaml .= "  $id: \"$char\"\n";
        }

        // Write to file
        $yamlPath = "$destdir/data.yaml";
        file_put_contents($yamlPath, $yaml);
    }


    public function saveAsYoloFmt($destdir = 'dataset', $fmt = 'png', $train = 0.7, $val = 0.2, $test = 0.1) {

        if (abs($train + $val + $test - 1) >= PHP_FLOAT_EPSILON)
            throw new InvalidArgumentException("weights must sum up to 1");

        if (!function_exists("image$fmt"))
            throw new InvalidArgumentException("function image$fmt doesnt exist");

        if (count($this->labels) === 0)
            throw new RuntimeException("Illegal state: build must be called first");


        $rand = mt_rand() / mt_getrandmax();

        if ($rand <= $train)
            $dest = 'train';
        elseif ($rand <= $train+$val)
            $dest = 'val';
        else
            $dest = 'test';

        $this->checkStructure("$destdir/images/$dest");
        $this->checkStructure("$destdir/labels/$dest");

        $imgok = "image$fmt"($this->contents, "$destdir/images/$dest/{$this->counter}.$fmt");
        if (!$imgok)
            throw new RuntimeException("Can't create image $destdir/images/$dest/{$this->counter}.$fmt");

        $f = fopen("$destdir/labels/$dest/{$this->counter}.txt", 'w');

        if (!$f)
            throw new RuntimeException("Can't create label $destdir/labels/$dest/{$this->counter}.txt");

        foreach ($this->labels as $lp) {
            $normCx = $lp->scx / $this->width;
            $normCy = $lp->scy / $this->height;
            $normW = $lp->sw / $this->width;
            $normH = $lp->sh / $this->height;

            $yoloLine = sprintf("%.6f %.6f %.6f %.6f\n", $normCx, $normCy, $normW, $normH);

            fwrite($f, $this->chartoidx[$lp->label] . " $yoloLine");
        }

        fclose($f);


        $this->counter++;
    }

    private function rotate_point($px, $py, $cx, $cy, $angle_degrees) {
        $angle = deg2rad($angle_degrees); // Convert degrees to radians
        $cos = cos($angle);
        $sin = sin($angle);

        // Translate point to origin
        $tx = $px - $cx;
        $ty = $py - $cy;

        // Rotate point
        $rx = $tx * $cos - $ty * $sin;
        $ry = $tx * $sin + $ty * $cos;

        // Translate point back
        $newX = $rx + $cx;
        $newY = $ry + $cy;

        return [$newX, $newY];
    }

    /**
     * Writes the phrase on the image
     */
    protected function writePhrase($image, $phrase, $font, $width, $height)
    {
        $this->labels = [];

        $length = mb_strlen($phrase);
        if ($length === 0) {
            return \imagecolorallocate($image, 0, 0, 0);
        }

        // Gets the text size and start position
        $size = (int) round($width / $length) - $this->rand(0, 3) - 1;
        $box = \imagettfbbox($size, 0, $font, $phrase);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = (int) round(($width - $textWidth) / 2);
        $y = (int) round(($height - $textHeight) / 2) + $size;

        if (!$this->textColor) {
            $textColor = array($this->rand(0, 150), $this->rand(0, 150), $this->rand(0, 150));
        } else {
            $textColor = $this->textColor;
        }
        $col = \imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);

        // Write the letters one by one, with random angle
        for ($i=0; $i<$length; $i++) {
            $symbol = mb_substr($phrase, $i, 1);
            $box = \imagettfbbox($size, 0, $font, $symbol);

            $w = $box[2] - $box[0];
            $h = $box[3] - $box[5];

            $angle = $this->rand(-$this->maxAngle, $this->maxAngle);
            $offset = $this->rand(-$this->maxOffset, $this->maxOffset);

            \imagettftext($image, $size, $angle, $x, $y + $offset, $col, $font, $symbol);

            $llx = $x;
            $lly = $y + $offset;

            $urx = $llx + $w;
            $ury = $lly - $h;

            $lrx = $urx;
            $lry = $lly;

            $ulx = $llx;
            $uly = $ury;

            $cx = $ulx + (int)round($w * 0.5);
            $cy = $uly + (int)round($h * 0.5);


            [$llx, $lly] = $this->rotate_point($llx, $lly, $cx, $cy, -$angle);
            [$urx, $ury] = $this->rotate_point($urx, $ury, $cx, $cy, -$angle);
            [$lrx, $lry] = $this->rotate_point($lrx, $lry, $cx, $cy, -$angle);
            [$ulx, $uly] = $this->rotate_point($ulx, $uly, $cx, $cy, -$angle);

            if (false) {
                // Colors
                $green = imagecolorallocate($image, 0, 255, 0);

                // Draw lines connecting the corners
                imageline($image, $llx, $lly, $lrx, $lry, $green);    // Lower left to lower right
                imageline($image, $lrx, $lry, $urx, $ury, $green);   // Lower right to upper right
                imageline($image, $urx, $ury, $ulx, $uly, $green);   // Upper right to upper left
                imageline($image, $ulx, $uly, $llx, $lly, $green);    // Upper left to lower left
                // Optional: mark the center point
                imagesetpixel($image, $cx, $cy, $green);
            }



            $sllx = $sulx = min($ulx, $llx);
            $surx = $slrx = max($urx, $lrx);
            $slly = $slry = max($lly, $lry);
            $suly = $sury = min($uly, $ury);

            $sw = $surx - $sulx;
            $sh = $slly - $suly;

            $scx = $sulx + (int)round($sw * 0.5);
            $scy = $suly + (int)round($sh * 0.5);


            if (false) {

                $blue = imagecolorallocate($image, 0, 0, 255);

                imageline($image, $sulx, $suly, $surx, $sury, $blue);    // upper left to upper right
                imageline($image, $sllx, $slly, $slrx, $slry, $blue);   // lower left to lower right
                imageline($image, $sulx, $suly, $sllx, $slly, $blue);   // upper left to lower left
                imageline($image, $surx, $sury, $slrx, $slry, $blue);   // upper right to lower right

                imagesetpixel($image, $scx, $scy, $blue);
            }

            // TODO: distortion

            $pos = new LabelPos($symbol);

            $pos->llx = $llx;
            $pos->lly = $lly;
            $pos->lrx = $lrx;
            $pos->lry = $lry;
            $pos->urx = $urx;
            $pos->ury = $ury;
            $pos->ulx = $ulx;
            $pos->uly = $uly;

            $pos->cx = $cx;
            $pos->cy = $cy;

            $pos->w = $w;
            $pos->h = $h;

            $pos->sllx = $sllx;
            $pos->slly = $slly;
            $pos->slrx = $slrx;
            $pos->slry = $slry;
            $pos->sulx = $sulx;
            $pos->suly = $suly;
            $pos->surx = $surx;
            $pos->sury = $sury;

            $pos->sw = $sw;
            $pos->sh = $sh;

            $pos->scx = $scx;
            $pos->scy = $scy;


            array_push($this->labels, $pos);


            $x += $w;
        }

        return $col;
    }

    /**
     * Generate the image
     */
    public function build($width = 150, $height = 40, $font = null, $fingerprint = null)
    {

        $this->phrase = $this->builder->build();

        $this->width = $width;
        $this->height = $height;

        if (null !== $fingerprint) {
            $this->fingerprint = $fingerprint;
            $this->useFingerprint = true;
        } else {
            $this->fingerprint = array();
            $this->useFingerprint = false;
        }

        if ($font === null) {
            $font = __DIR__ . '/Font/captcha'.$this->rand(0, 5).'.ttf';
        }

        if (empty($this->backgroundImages)) {
            // if background images list is not set, use a color fill as a background
            $image   = imagecreatetruecolor($width, $height);
            if ($this->backgroundColor == null) {
                $bg = imagecolorallocate($image, $this->rand(200, 255), $this->rand(200, 255), $this->rand(200, 255));
            } else {
                $color = $this->backgroundColor;
                $bg = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            }
            imagefill($image, 0, 0, $bg);
        } else {
            // use a random background image
            $randomBackgroundImage = $this->backgroundImages[rand(0, count($this->backgroundImages)-1)];

            $imageType = $this->validateBackgroundImage($randomBackgroundImage);

            $image = $this->createBackgroundImageFromType($randomBackgroundImage, $imageType);
        }

        // Apply effects
        if (!$this->ignoreAllEffects) {
            $square = $width * $height;
            $effects = $this->rand($square/3000, $square/2000);

            // set the maximum number of lines to draw in front of the text
            if ($this->maxBehindLines != null && $this->maxBehindLines > 0) {
                $effects = min($this->maxBehindLines, $effects);
            }

            if ($this->maxBehindLines !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine($image, $width, $height);
                }
            }
        }

        // Write CAPTCHA text
        $color = $this->writePhrase($image, $this->phrase, $font, $width, $height);

        // Apply effects
        if (!$this->ignoreAllEffects) {
            $square = $width * $height;
            $effects = $this->rand($square/3000, $square/2000);

            // set the maximum number of lines to draw in front of the text
            if ($this->maxFrontLines != null && $this->maxFrontLines > 0) {
                $effects = min($this->maxFrontLines, $effects);
            }

            if ($this->maxFrontLines !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine($image, $width, $height, $color);
                }
            }
        }

        // Distort the image
        if ($this->distortion && !$this->ignoreAllEffects) {
            $image = $this->distort($image, $width, $height, $bg);
        }

        // Post effects
        if (!$this->ignoreAllEffects) {
            $this->postEffect($image);
        }

        $this->contents = $image;

        return $this;
    }

    /**
     * Distorts the image
     */
    public function distort($image, $width, $height, $bg)
    {
        $contents = imagecreatetruecolor($width, $height);
        $X          = $this->rand(0, $width);
        $Y          = $this->rand(0, $height);
        $phase      = $this->rand(0, 10);
        $scale      = 1.1 + $this->rand(0, 10000) / 30000;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $Vx = $x - $X;
                $Vy = $y - $Y;
                $Vn = sqrt($Vx * $Vx + $Vy * $Vy);

                if ($Vn != 0) {
                    $Vn2 = $Vn + 4 * sin($Vn / 30);
                    $nX  = $X + ($Vx * $Vn2 / $Vn);
                    $nY  = $Y + ($Vy * $Vn2 / $Vn);
                } else {
                    $nX = $X;
                    $nY = $Y;
                }
                $nY = $nY + $scale * sin($phase + $nX * 0.2);

                if ($this->interpolation) {
                    $p = $this->interpolate(
                        $nX - floor($nX),
                                            $nY - floor($nY),
                                            $this->getCol($image, floor($nX), floor($nY), $bg),
                                            $this->getCol($image, ceil($nX), floor($nY), $bg),
                                            $this->getCol($image, floor($nX), ceil($nY), $bg),
                                            $this->getCol($image, ceil($nX), ceil($nY), $bg)
                    );
                } else {
                    $p = $this->getCol($image, round($nX), round($nY), $bg);
                }

                if ($p == 0) {
                    $p = $bg;
                }

                imagesetpixel($contents, $x, $y, $p);
            }
        }

        return $contents;
    }

}
