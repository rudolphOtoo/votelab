<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Reporting\ExportResultsAction;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Support\Organizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportResultsController
{
    public function __invoke(Request $request, AwardProgram $program, ExportResultsAction $export): StreamedResponse
    {
        $category = $request->filled('category')
            ? AwardCategory::query()->where('award_program_id', $program->id)->findOrFail($request->integer('category'))
            : null;

        return $export->execute($program, Organizer::user(), $category);
    }
}
