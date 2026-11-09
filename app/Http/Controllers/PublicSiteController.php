<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebsiteInquiryNotification;
use App\Models\WebsiteInquiry;
use App\Website\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PublicSiteController extends Controller
{
    private const VIEWS = ['site.home' => 'site.home', 'site.features' => 'site.features', 'site.how' => 'site.how-it-works', 'site.pricing' => 'site.pricing', 'site.security' => 'site.security', 'site.faq' => 'site.faq', 'site.contact' => 'site.contact'];

    public function page(Request $r)
    {
        $name = $r->route()->getName();

        return response()->view(self::VIEWS[$name], ['routeName' => $name]);
    }

    public function legal(Request $r)
    {
        $name = $r->route()->getName();
        $doc = $name === 'site.privacy' ? 'privacy' : 'terms';

        return response()->view('site.legal', ['routeName' => $name, 'doc' => $doc, 'heading' => $doc === 'privacy' ? 'Privacy Policy' : 'Terms of Service']);
    }

    public function submit(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|string|email:rfc|max:190',
            'company' => 'required|string|max:150',
            'role' => 'nullable|string|max:120',
            'inquiry_type' => ['required', Rule::in(array_keys(config('website.inquiry_types')))],
            'message' => 'required|string|min:10|max:5000',
            'homepage_url' => 'nullable|string|max:500',
        ], [], ['email' => 'work email', 'inquiry_type' => 'inquiry type']);

        $clean = fn (?string $v) => $v === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v));
        $message = $clean($data['message']);
        $spam = filled($data['homepage_url'] ?? null) ? 'honeypot' : (preg_match_all('~https?://|www\.~i', $message) > 3 ? 'link_heavy' : null);
        $notify = config('website.inquiry_notify_email');

        try {
            $inquiry = WebsiteInquiry::create([
                'name' => $clean($data['name']),
                'email' => mb_strtolower($clean($data['email'])),
                'company' => $clean($data['company']),
                'role' => $clean($data['role'] ?? null) ?: null,
                'inquiry_type' => $data['inquiry_type'],
                'message' => $message,
                'status' => 'new',
                'is_spam' => $spam !== null,
                'spam_reason' => $spam,
                'source_hash' => hash_hmac('sha256', (string) $r->ip(), (string) config('app.key')),
                'notification_status' => $spam ? 'skipped_spam' : ($notify ? 'queued' : 'not_configured'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Website inquiry could not be saved', ['exception_type' => get_class($e)]);

            return back()->withInput()->withErrors(['form' => 'We could not save your request. Please try again later.']);
        }

        if (! $spam && $notify) {
            try {
                SendWebsiteInquiryNotification::dispatch($inquiry->id);
            } catch (\Throwable $e) {
                // The inquiry is already saved; delivery problems are tracked for staff, never shown to visitors.
                Log::warning('Website inquiry notification could not be queued', ['inquiry_id' => $inquiry->id, 'exception_type' => get_class($e)]);
                WebsiteInquiry::whereKey($inquiry->id)->whereIn('notification_status', ['queued'])->update(['notification_status' => 'dispatch_failed', 'notification_error' => class_basename($e)]);
            }
        }

        return redirect()->route('site.contact')->with('inquiry_saved', true);
    }

    public function sitemap()
    {
        $pages = Site::pages();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (Site::sitemapRoutes() as $route) {
            $xml .= '  <url><loc>'.e(Site::url($pages[$route][0])).'</loc></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = Site::indexable()
            ? ['User-agent: *', 'Allow: /', 'Disallow: /api/', 'Disallow: /dashboard', 'Disallow: /files/', 'Disallow: /settings/', 'Disallow: /platform', 'Disallow: /horizon', '', 'Sitemap: '.Site::url('/sitemap.xml')]
            : ['# Indexing disabled for this environment.', 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
