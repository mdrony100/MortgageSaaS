<?php

namespace App\Http\Controllers\Admin;

use App\Bank\DocumentParser;
use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;

/** Platform-admin integration settings. Responses never include secret values. */
class SettingsController extends Controller
{
    public function show(PlatformSettings $settings)
    {
        return $this->payload($settings);
    }

    public function update(Request $request, PlatformSettings $settings)
    {
        $changed = $settings->update($request->validate($settings->rules()));

        return $this->payload($settings) + ['changed' => $changed];
    }

    private function payload(PlatformSettings $settings): array
    {
        return ['settings' => $settings->view(), 'parser' => app(DocumentParser::class)->diagnose()];
    }

    public function test(Request $request, PlatformSettings $settings)
    {
        $check = $request->validate(['check' => 'required|in:smtp,storage,openai,clamav,python'])['check'];

        return $settings->probe($check);
    }

    public function testEmail(Request $request, PlatformSettings $settings)
    {
        $result = $settings->sendTestEmail($request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
