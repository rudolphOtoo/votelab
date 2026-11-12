<?php

declare(strict_types=1);

namespace App\Support\Images;

/**
 * How a run of text is drawn on a share image.
 */
final readonly class TextStyle
{
    /**
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    public function __construct(
        public int $size,
        public array $rgb,
        public bool $bold = false,
        public int $maxLines = 1,
    ) {}
}
