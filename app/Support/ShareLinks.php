<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;

/**
 * A nominee's share kit: public deep link, vote code, WhatsApp share link with prefilled text, and the
 * campaign image URLs. Never vote counts.
 */
final class ShareLinks
{
    /**
     * @return array{url: string, code: string, text: string, whatsapp: string, images: array<string, string>}
     */
    public static function for(Nominee $nominee): array
    {
        $nominee->loadMissing(['awardCategory', 'awardProgram.organization']);
        $program = $nominee->awardProgram;
        $parameters = [
            'organization' => $program->organization->slug,
            'awardProgram' => $program->slug,
            'nominee' => $nominee->nominee_code,
        ];
        $url = route('public.nominee', $parameters);
        $text = "Vote for {$nominee->displayName()} in {$nominee->awardCategory->name} at {$program->title}. Code: {$nominee->nominee_code}. {$url}";

        $images = [];

        foreach (array_keys(ShareImages::FORMATS) as $format) {
            $images[$format] = route('public.nominee.share', [...$parameters, 'format' => $format]);
        }

        return [
            'url' => $url,
            'code' => $nominee->nominee_code,
            'text' => $text,
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($text),
            'images' => $images,
        ];
    }
}
