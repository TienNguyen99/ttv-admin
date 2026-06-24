<?php

namespace App\Http\Controllers;

use App\Support\LocalQrCode;
use Illuminate\Http\Request;

class LocalQrCodeController extends Controller
{
    public function png(Request $request)
    {
        $text = (string) $request->query('text', '');
        if (trim($text) === '') {
            abort(422, 'Missing QR text.');
        }

        $png = LocalQrCode::png(
            $text,
            (int) $request->query('size', 180),
            (int) $request->query('margin', 6)
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
