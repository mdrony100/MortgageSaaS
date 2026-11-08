<?php

namespace App\Http\Middleware;

use App\Models\Bank\Run;
use App\Models\LoanFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SerializeBankReview
{
    public function handle($request, \Closure $next)
    {
        $file = $request->route('file');
        abort_unless($file instanceof LoanFile, 404);
        Gate::authorize('review', $file);

        return DB::transaction(function () use ($request, $file, $next) {
            Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->whereKey($request->route('runId'))->lockForUpdate()->firstOrFail();

            return $next($request);
        });
    }
}
