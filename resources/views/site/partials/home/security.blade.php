<section class="site-section bg-ink text-white" aria-labelledby="security-heading">
    <div class="site-container site-story">
        <div data-reveal>
            <p class="site-eyebrow !text-teal-200">Security</p>
            <h2 id="security-heading" class="site-h2 mt-3 !text-white">Keep sensitive files in the right hands.</h2>
            <p class="site-lead mt-4 !text-slate-200">Your company’s files stay private. Access depends on each person’s role and the files shared with them.</p>
            <a href="{{ route('site.security') }}" class="site-btn site-btn-light mt-8">Read how we protect files <span aria-hidden="true">→</span></a>
        </div>
        <div class="relative overflow-hidden rounded-[1.5rem] border border-white/10 bg-gradient-to-br from-[#16395a] to-[#0b2034] p-6 sm:p-8" data-reveal>
            <ul class="relative grid gap-5">
                @foreach ([
                    ['Private file access', 'Documents are stored privately and only open for signed-in people who have access to that file.', 'M7 11V7a5 5 0 0110 0v4M5 11h14v10H5z'],
                    ['Team permissions', 'Owners, admins, underwriters, processors and brokers each have their own permissions. Sharing a file never raises anyone’s role.', 'M16 11a4 4 0 10-8 0M4 21a8 8 0 0116 0M12 7a3 3 0 100-6'],
                    ['Two-factor sign-in', 'People can protect their accounts with a code from an authenticator app.', 'M12 2l8 3v6c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V5l8-3zM9 12l2 2 4-4'],
                ] as [$title, $text, $icon])
                    <li class="flex gap-4 rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
                        <span class="inline-flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-teal-400/15 text-teal-200" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span>
                        <span><span class="block text-[1.25rem] font-bold text-white">{{ $title }}</span><span class="mt-1 block text-slate-200">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
