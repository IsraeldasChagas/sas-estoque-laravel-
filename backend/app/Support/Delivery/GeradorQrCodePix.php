<?php

namespace App\Support\Delivery;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Throwable;

final class GeradorQrCodePix
{
    public static function dataUriSvg(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        try {
            $qr = new QrCode(
                $payload,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 240,
                margin: 8,
            );
            $writer = new SvgWriter;
            $result = $writer->write($qr);

            return $result->getDataUri();
        } catch (Throwable) {
            return null;
        }
    }
}
