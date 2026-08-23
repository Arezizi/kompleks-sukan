<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders QR codes as inline SVG strings using the bacon-qr-code package
 * that ships with Fortify's two-factor support.
 */
final class QrCodeService
{
    /**
     * Render the given payload as a black-on-white SVG QR code.
     */
    public static function svg(string $payload, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        );

        /** @var string $svg */
        $svg = (new Writer($renderer))->writeString($payload);

        return $svg;
    }

    /**
     * The absolute counter URL encoded into a reservation pickup QR.
     */
    public static function reservationPickupUrl(string $token): string
    {
        return url(route('counter.checkout', ['code' => $token]));
    }

    /**
     * The absolute counter URL encoded into a loan return QR.
     */
    public static function loanReturnUrl(string $token): string
    {
        return url(route('counter.returns', ['loan' => $token]));
    }
}
