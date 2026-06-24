<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class LocalQrCode
{
    private static bool $autoloadRegistered = false;

    public static function png(string $text, int $size = 180, int $margin = 6): string
    {
        self::registerVendorAutoload();

        if (!class_exists('FPDF', false)) {
            eval('class FPDF {}');
        }

        $qrCode = new QrCode($text);
        $qrCode->setSize(max(64, min($size, 800)));
        $qrCode->setMargin(max(0, min($margin, 40)));
        $qrCode->setWriter(new PngWriter());
        $qrCode->setValidateResult(false);

        return $qrCode->writeString();
    }

    private static function registerVendorAutoload(): void
    {
        if (self::$autoloadRegistered) {
            return;
        }

        $basePath = base_path('vendor');
        $prefixes = [
            'Endroid\\QrCode\\' => $basePath . '/endroid/qr-code/src/',
            'BaconQrCode\\' => $basePath . '/bacon/bacon-qr-code/src/',
            'DASPRiD\\Enum\\' => $basePath . '/dasprid/enum/src/',
            'MyCLabs\\Enum\\' => $basePath . '/myclabs/php-enum/src/',
            'Symfony\\Component\\OptionsResolver\\' => $basePath . '/symfony/options-resolver/',
            'Symfony\\Component\\PropertyAccess\\' => $basePath . '/symfony/property-access/',
            'Symfony\\Component\\PropertyInfo\\' => $basePath . '/symfony/property-info/',
            'Zxing\\' => $basePath . '/khanamiryan/qrcode-detector-decoder/lib/',
        ];

        spl_autoload_register(function (string $class) use ($prefixes) {
            foreach ($prefixes as $prefix => $path) {
                if (strpos($class, $prefix) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, strlen($prefix));
                $file = $path . str_replace('\\', '/', $relativeClass) . '.php';

                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        });

        self::$autoloadRegistered = true;
    }
}
