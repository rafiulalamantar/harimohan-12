<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when an uploaded picture could not be written to storage (e.g. Supabase is down).
 * The "public" disk is set to 'throw' => false, so a failed upload returns false instead of
 * throwing; Memory::addPhotos turns that into this exception so the post is not saved half-done.
 */
class PhotoNotSavedException extends Exception
{
    public function __construct()
    {
        parent::__construct("Couldn't save the photos. Please try again in a minute.");
    }
}
