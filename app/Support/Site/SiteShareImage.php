<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Images\TextStyle;
use App\Support\Images\TextWriter;
use App\Support\ShareImages;
use GdImage;
use RuntimeException;

/**
 * The homepage's link-preview image (Open Graph, 1200×630): the Stage night look (basalt, a gold lattice, the VoteLab
 * mark) and the headline. Drawn with the share-image tools (bundled Instrument Sans) once, then served from the
 * public photo disk; VERSION is part of the file name, so a change to the drawing produces a new file.
 */
final class SiteShareImage
{
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    private const int VERSION = 3; // 2: the headline wraps instead of losing its last word; 3: more bottom margin

    private const array BASALT = [18, 19, 22];

    private const array INK = [245, 245, 244];

    private const array MUTED = [184, 177, 170];

    private const array GOLD = [251, 191, 36];

    /**
     * The image's public URL, drawing it first if it isn't stored yet.
     */
    public static function url(): string
    {
        $disk = ShareImages::disk();
        $path = 'share/site/votelab-og-v' . self::VERSION . '.jpg';

        if (!$disk->exists($path)) {
            $disk->put($path, self::render(), 'public');
        }

        return $disk->url($path);
    }

    private static function render(): string
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if (!$canvas instanceof GdImage) {
            throw new RuntimeException('Could not create the site share image.');
        }

        imagefill($canvas, 0, 0, TextWriter::color($canvas, self::BASALT));
        self::drawLattice($canvas);
        self::drawMark($canvas);
        self::drawText($canvas);

        ob_start();
        imagejpeg($canvas, null, 88);

        return (string) ob_get_clean();
    }

    /**
     * The hero's diamond lattice: small gold diamonds on a 36 px grid, faint.
     */
    private static function drawLattice(GdImage $canvas): void
    {
        $dot = (int) imagecolorallocatealpha($canvas, self::GOLD[0], self::GOLD[1], self::GOLD[2], 108);

        for ($y = 18; $y < self::HEIGHT; $y += 36) {
            for ($x = ((($y / 36) % 2) * 18) + 18; $x < self::WIDTH; $x += 36) {
                imagefilledpolygon($canvas, [$x, $y - 3, $x + 3, $y, $x, $y + 3, $x - 3, $y], $dot);
            }
        }
    }

    /**
     * The VoteLab mark (a square with a gold tick) and the name.
     */
    private static function drawMark(GdImage $canvas): void
    {
        $gold = TextWriter::color($canvas, self::GOLD);
        imagesetthickness($canvas, 6);
        imagerectangle($canvas, 80, 80, 136, 136, TextWriter::color($canvas, self::INK));
        imagesetthickness($canvas, 8);
        imageline($canvas, 94, 110, 104, 120, $gold);
        imageline($canvas, 104, 120, 124, 96, $gold);
        TextWriter::draw($canvas, 'VoteLab', [160, 92, 600], new TextStyle(44, self::INK, bold: true));
    }

    /**
     * The headline and its supporting line. TextWriter draws one line unless told otherwise, dropping the rest, so the
     * headline gets two.
     */
    private static function drawText(GdImage $canvas): void
    {
        $y = TextWriter::draw(
            $canvas,
            'Vote for your favourites.',
            [80, 190, 1040],
            new TextStyle(70, self::INK, bold: true, maxLines: 2),
        );
        $y = TextWriter::draw(
            $canvas,
            'Watch them win.',
            [80, $y + 4, 1040],
            new TextStyle(70, self::GOLD, bold: true),
        );
        TextWriter::draw(
            $canvas,
            'Awards voting in Ghana. Mobile money or card, every vote counted once.',
            [80, $y + 24, 1040],
            new TextStyle(30, self::MUTED, maxLines: 2),
        );
    }
}
