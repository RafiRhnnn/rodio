<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an audio conversion cannot be completed.
 *
 * The message is always a safe, user-presentable sentence: raw FFmpeg output
 * never travels with it (it goes to the log instead).
 */
class AudioConversionFailed extends RuntimeException
{
}
