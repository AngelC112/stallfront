<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR dell’ordine in PNG, per le email: molti programmi di posta non mostrano gli SVG.
 * Disegnato con GD, così non serve l’estensione Imagick.
 */
class OrderQrCode
{
    private const MODULE_PIXELS = 8;

    private const QUIET_ZONE_MODULES = 4;

    /**
     * PNG del QR con il solo codice ordine, oppure null se GD non è disponibile.
     */
    public static function png(string $orderNumber): ?string
    {
        if (! function_exists('imagecreate')) {
            return null;
        }

        $matrix = Encoder::encode($orderNumber, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ECODING)->getMatrix();
        $modules = $matrix->getWidth();
        $size = ($modules + self::QUIET_ZONE_MODULES * 2) * self::MODULE_PIXELS;

        $image = imagecreate($size, $size);
        imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $left = ($x + self::QUIET_ZONE_MODULES) * self::MODULE_PIXELS;
                    $top = ($y + self::QUIET_ZONE_MODULES) * self::MODULE_PIXELS;
                    imagefilledrectangle($image, $left, $top, $left + self::MODULE_PIXELS - 1, $top + self::MODULE_PIXELS - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
