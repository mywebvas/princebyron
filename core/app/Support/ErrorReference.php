<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * ErrorReference — a short, quotable id attached to a failed request.
 *
 * WHY THIS EXISTS (part of P2-3)
 * ------------------------------
 * A 500 page that says only "Something went wrong" is a dead end: the customer
 * cannot report anything useful, and support cannot find the log line. A short
 * reference printed on the page and written into the log turns a support thread
 * from "it didn't work, yesterday, maybe the afternoon" into a single lookup.
 *
 * It is deliberately NOT the stack trace, the request id or anything derived
 * from user input: it is a random 8-character token, so printing it on a public
 * page leaks nothing and cannot be guessed into someone else's incident.
 *
 * The value lives on the request as an attribute so the error view can render it
 * without regenerating it, and so a later middleware could attach it to a
 * response header for the same reason.
 */
class ErrorReference
{
    public const ATTRIBUTE = 'error_reference';

    /** The reference for this request, generating it on first use. */
    public function current(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request->attributes->has(self::ATTRIBUTE)) {
            $request->attributes->set(self::ATTRIBUTE, strtoupper(Str::random(8)));
        }

        return $request->attributes->get(self::ATTRIBUTE);
    }
}
