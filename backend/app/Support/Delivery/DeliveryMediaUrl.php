<?php

namespace App\Support\Delivery;

final class DeliveryMediaUrl
{
    public static function fromPublicPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || str_contains($rel, '..') || ! str_starts_with($rel, 'uploads/delivery/')) {
            return null;
        }

        if (! is_file(public_path($rel))) {
            return null;
        }

        $host = rtrim((string) request()->getSchemeAndHttpHost(), '/');

        return $host.'/'.$rel;
    }
}
