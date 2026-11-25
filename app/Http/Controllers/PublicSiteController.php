<?php

namespace App\Http\Controllers;

use App\Billing\CreditNotifier;
use App\Jobs\SendWebsiteInquiryNotification;
use App\Models\DemoRequest;
use App\Models\WebsiteInquiry;
use App\Website\CallingCodes;
use App\Website\DemoCaptcha;
use App\Website\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublicSiteController extends Controller
{
    private const VIEWS = ['site.home' => 'site.home', 'site.features' => 'site.features', 'site.how' => 'site.how-it-works', 'site.pricing' => 'site.pricing', 'site.security' => 'site.security', 'site.faq' => 'site.faq', 'site.contact' => 'site.contact'];

    public function page(Request $r)
    {
        $name = $r->route()->getName();
        $data = ['routeName' => $name];
        if ($name === 'site.contact') {
            $data['captcha'] = DemoCaptcha::current(DemoCaptcha::CONTACT_KEY);
        }

        return response()->view(self::VIEWS[$name], $data);
    }

    public function legal(Request $r)
    {
        $name = $r->route()->getName();
        $doc = $name === 'site.privacy' ? 'privacy' : 'terms';

        return response()->view('site.legal', ['routeName' => $name, 'doc' => $doc, 'heading' => $doc === 'privacy' ? 'Privacy Policy' : 'Terms of Service']);
    }

    public function submit(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|string|email:rfc|max:190',
            'company' => 'required|string|max:150',
            'role' => 'nullable|string|max:120',
            'inquiry_type' => ['required', Rule::in(array_keys(config('website.inquiry_types')))],
            'message' => 'required|string|min:10|max:5000',
            'homepage_url' => 'nullable|string|max:500',
            'captcha' => 'required|string|max:2',
        ], [
            'captcha.required' => 'Answer the question so we know this is a person.',
        ], [
            'email' => 'work email',
            'inquiry_type' => 'inquiry type',
            'captcha' => 'question',
        ]);

        $validator->fails();
        if (! DemoCaptcha::matches($r->input('captcha'), DemoCaptcha::CONTACT_KEY)) {
            $validator->errors()->add('captcha', 'That answer does not match. Please try the new question.');
        }
        if ($validator->errors()->isNotEmpty()) {
            DemoCaptcha::refresh(DemoCaptcha::CONTACT_KEY);

            return back()->withInput($r->except('captcha'))->withErrors($validator);
        }

        $data = $validator->validated();
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
            DemoCaptcha::refresh(DemoCaptcha::CONTACT_KEY);

            return back()->withInput($r->except('captcha'))->withErrors(['form' => 'We could not save your request. Please try again later.']);
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

        DemoCaptcha::forget(DemoCaptcha::CONTACT_KEY);

        return redirect()->route('site.contact')->with('inquiry_saved', true);
    }

    public function demo()
    {
        return response()->view('site.demo', [
            'routeName' => 'site.demo',
            'captcha' => DemoCaptcha::current(),
            'countries' => CallingCodes::all(),
        ]);
    }

    public function demoSubmit(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'name' => 'required|string|max:120',
            'company' => 'required|string|max:150',
            'email' => 'required|string|email:rfc|max:190',
            'phone_country' => 'required|string|size:2',
            'phone' => 'required|string|max:30',
            'monthly_volume' => 'nullable|string|max:120',
            'captcha' => 'required|string|max:2',
            'company_website' => 'nullable|string|max:500',
        ], [
            'captcha.required' => 'Answer the question so we know this is a person.',
        ], [
            'email' => 'work email',
            'monthly_volume' => 'monthly loan volume or pages',
            'phone' => 'phone',
            'phone_country' => 'country',
            'captcha' => 'question',
        ]);

        $validator->fails();
        $e164 = CallingCodes::e164($r->input('phone_country'), $r->input('phone'));
        if (! $e164) {
            $validator->errors()->add('phone', 'Enter a valid phone number for the selected country.');
        }
        if (! DemoCaptcha::matches($r->input('captcha'))) {
            $validator->errors()->add('captcha', 'That answer does not match. Please try the new question.');
        }
        if ($validator->errors()->isNotEmpty()) {
            DemoCaptcha::refresh();

            return back()->withInput($r->except('captcha'))->withErrors($validator);
        }

        $clean = fn (?string $v) => $v === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
        $volume = $clean($r->input('monthly_volume'));
        $volume = $volume === '' ? null : $volume;
        $spam = filled($r->input('company_website')) ? 'honeypot' : ($volume !== null && preg_match_all('~https?://|www\.~i', $volume) > 3 ? 'link_heavy' : null);

        try {
            $demo = DemoRequest::create([
                'name' => $clean($r->input('name')),
                'company' => $clean($r->input('company')),
                'email' => mb_strtolower((string) $clean($r->input('email'))),
                'phone' => $e164,
                'monthly_volume' => $volume,
                'message' => null,
                'status' => 'new',
                'is_spam' => $spam !== null,
                'spam_reason' => $spam,
                'source_hash' => hash_hmac('sha256', (string) $r->ip(), (string) config('app.key')),
                'notification_status' => $spam ? 'skipped_spam' : 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('Demo request could not be saved', ['exception_type' => get_class($e)]);
            DemoCaptcha::refresh();

            return back()->withInput($r->except('captcha'))->withErrors(['form' => 'We could not save your request. Please try again later.']);
        }

        DemoCaptcha::forget();
        if (! $spam) {
            try {
                CreditNotifier::demoReceived($demo->fresh());
            } catch (\Throwable $e) {
                Log::warning('Demo request notification could not be sent', ['demo_request_id' => $demo->id, 'exception_type' => get_class($e)]);
                DemoRequest::whereKey($demo->id)->update(['notification_status' => 'dispatch_failed', 'notification_error' => class_basename($e)]);
            }
        }

        return redirect()->route('site.demo')->with('demo_saved', true);
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
            ? ['User-agent: *', 'Allow: /', 'Disallow: /api/', 'Disallow: /dashboard', 'Disallow: /files/', 'Disallow: /settings/', 'Disallow: /support', 'Disallow: /admin', 'Disallow: /platform', 'Disallow: /horizon', '', 'Sitemap: '.Site::url('/sitemap.xml')]
            : ['# Indexing disabled for this environment.', 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
