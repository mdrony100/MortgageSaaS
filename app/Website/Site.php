<?php

namespace App\Website;

/**
 * Central helpers for the public marketing website. All business content that
 * requires approval (prices, legal identity, contact details) comes from
 * config/website.php; nothing here invents commercial or legal facts.
 */
class Site
{
    /** Public marketing routes: route name => [path, title, description]. */
    public static function pages(): array
    {
        $p = self::name();

        return [
            'site.home' => ['/', $p.' — Mortgage document analysis with reviewer control', 'Review bank statements, spot potential document issues, and check appraisal and rental income details—all in one workspace, with supporting evidence and reviewer control.'],
            'site.features' => ['/features', 'Features — '.$p, 'Bank statement income analysis, document integrity indicators, appraisal review for forms 1004, 1025, 1007 and 216, rental/DSCR analysis and team workflow.'],
            'site.how' => ['/how-it-works', 'How It Works — '.$p, 'From organization setup and secure upload to analysis, reviewer decisions and authorized report exports in five steps.'],
            'site.pricing' => ['/pricing', 'Pricing — '.$p, 'Pricing for '.$p.' is provided on request. Request a demo or contact sales to discuss your team.'],
            'site.security' => ['/security', 'Security — '.$p, 'How '.$p.' protects documents with private storage, role and file permissions, organization isolation and append-only audit history.'],
            'site.faq' => ['/faq', 'FAQ — '.$p, 'Answers about supported documents, statement periods, appraisal forms, rental income, DSCR, exports, permissions and lending decisions.'],
            'site.contact' => ['/contact', 'Contact and Demo Requests — '.$p, 'Request a demo of '.$p.' or send a question to the team.'],
            'site.privacy' => ['/privacy', 'Privacy Policy — '.$p, 'Privacy Policy for '.$p.'.'],
            'site.terms' => ['/terms', 'Terms of Service — '.$p, 'Terms of Service for '.$p.'.'],
        ];
    }

    public static function name(): string
    {
        return (string) config('website.product_name', 'MortgageLens');
    }

    public static function url(string $path = '/'): string
    {
        return config('website.url').($path === '/' ? '/' : $path);
    }

    /** Indexing requires production AND explicit opt-in; draft legal pages are never indexable. */
    public static function indexable(?string $route = null): bool
    {
        if (! config('website.indexing') || ! app()->environment('production')) {
            return false;
        }
        if (in_array($route, ['site.privacy', 'site.terms'], true) && ! config('website.legal.approved')) {
            return false;
        }

        return $route === null || array_key_exists($route, self::pages());
    }

    /** Routes that belong in the public sitemap (never auth or application pages). */
    public static function sitemapRoutes(): array
    {
        return array_values(array_filter(array_keys(self::pages()), fn ($r) => ! in_array($r, ['site.privacy', 'site.terms'], true) || config('website.legal.approved')));
    }

    /** Only explicitly approved plans can be published. */
    public static function publishedPlans(): array
    {
        if (! config('website.pricing.published')) {
            return [];
        }

        return array_values(array_filter((array) config('website.pricing.plans', []), fn ($p) => is_array($p) && ($p['approved'] ?? false) === true && filled($p['name'] ?? null) && filled($p['price_label'] ?? null)));
    }

    /** Short, plain-English FAQ used on the homepage (each answer verified against the implementation). */
    public static function homeFaq(): array
    {
        $p = self::name();

        return [
            ['What documents can I upload?', 'Bank statements, appraisal reports (forms 1004, 1025, 1007 and 216), leases and other supporting documents. Files can be PDF, PNG or JPG, up to 20 MB each. Each file must pass a malware scan before anyone can open or process it.'],
            ['Can I review and correct extracted information?', 'Yes. Reviewers can correct extracted values, change how deposits are classified, and confirm or reject possible transfers. The original value and the reason for each change are kept in the file’s history. Broker accounts can’t change results.'],
            ['Does the platform make lending decisions?', 'No. '.$p.' organizes documents, calculations and potential issues so your team can review them. It doesn’t approve or decline loans, and it doesn’t replace underwriting judgment.'],
            ['What reports can I export?', 'Bank statement reports as Excel (XLSX), CSV and PDF. Appraisal and rental income reviews as PDF and Excel. Reports are versioned, and broker reports leave out restricted internal findings.'],
            ['Can my team work on the same file?', 'Yes. Invite teammates, give each person a role, and share individual files. Sharing a file never raises anyone’s role, and changes are recorded in the file’s history.'],
        ];
    }

    /**
     * Customer reviews for the public site. Only entries explicitly marked approved in
     * config/website.php are ever shown. When none are approved, non-production
     * environments show clearly labelled placeholders for design review, and production
     * hides the section entirely.
     *
     * @return array{mode: 'approved'|'placeholder'|'none', items: array<int, array<string, mixed>>}
     */
    public static function reviews(): array
    {
        $approved = array_values(array_filter((array) config('website.testimonials', []), fn ($t) => is_array($t)
            && ($t['approved'] ?? false) === true
            && filled($t['quote'] ?? null) && filled($t['name'] ?? null) && filled($t['role'] ?? null)));
        if ($approved) {
            return ['mode' => 'approved', 'items' => $approved];
        }
        if (app()->environment('production')) {
            return ['mode' => 'none', 'items' => []];
        }

        return ['mode' => 'placeholder', 'items' => [
            ['quote' => 'Approved quote goes here.', 'name' => 'Approved customer name', 'role' => 'Role, Company (approved)'],
            ['quote' => 'Approved quote goes here.', 'name' => 'Approved customer name', 'role' => 'Role, Company (approved)'],
        ]];
    }

    public static function faq(): array
    {
        return [
            ['Who is '.self::name().' designed for?', 'Mortgage brokers, loan officers, processors and underwriting teams who need to review borrower documents, calculations and flagged issues in one shared, permission-controlled workspace.'],
            ['Which statement periods and document formats are supported?', 'Bank statement analysis is configured for 12 or 24 months of statements. Documents are uploaded as PDF, PNG or JPG files (up to 20 MB each). Statements are read by a built-in native parser first; optional AI-assisted extraction for pages the parser cannot fully read is used only when an administrator configures and approves it.'],
            ['Can reviewers correct results or override classifications?', 'Yes. Authorized reviewers can correct extracted values, change deposit classifications, confirm or reject transfer matches and resolve findings. Each change records a reason and is kept in the review history alongside the original value. Broker accounts cannot override results.'],
            ['How are potential fraud indicators handled?', 'Indicators such as reconciliation differences, page numbering gaps, duplicate pages or PDF structure observations are shown as potential issues that require human review, with links to the source evidence. They are not proof of fraud or alteration, and reviewers record their own decision on each finding.'],
            ['Which appraisal forms and property types are supported?', 'Appraisal review supports forms 1004, 1025, 1007 and 216 for residential properties with 1–4 units. Documents outside that scope are flagged for manual review rather than being evaluated automatically.'],
            ['How are rental income and DSCR calculated?', 'Lease and unit-level rents are compared with market rent from the appraisal documents. A reviewer confirms the qualifying rent for each unit, and DSCR is calculated deterministically from those confirmed inputs and the confirmed monthly payment. When information is missing, results are marked provisional and the missing items are listed.'],
            ['Can I export reports to Excel and PDF?', 'Yes. Bank statement reports can be exported as Excel (XLSX), CSV and PDF, and appraisal and rental/DSCR review reports as PDF and Excel. Issued reports are versioned, and Broker-facing reports exclude restricted internal findings.'],
            ['How do team access and permissions work?', 'Each organization has its own members and roles. Access to a loan file requires both an appropriate role and access to that file, either organization-wide or through an explicit share. Sharing a file never raises a member’s role.'],
            ['Does '.self::name().' make lending decisions?', 'No. '.self::name().' organizes documents, calculations and potential issues to support professional review. It does not approve or decline loans, issue lender approvals or replace underwriting judgment.'],
            ['Can I connect bank accounts directly?', 'Not yet. Direct bank connections (for example through Plaid) are planned but not available today. Statements are currently analyzed from uploaded documents.'],
        ];
    }
}
