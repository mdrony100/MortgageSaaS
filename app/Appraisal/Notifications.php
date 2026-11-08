<?php

namespace App\Appraisal;

use App\Mail\ReviewReady;
use App\Models\LoanFile;
use App\Models\Review\Run;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class Notifications
{
    public function ready(Run $r): void
    {
        if (! config('review.email_notifications')) {
            return;
        }$user = User::find($r->requested_by);
        $file = LoanFile::find($r->loan_file_id);
        if (! $user || ! $user->email_verified_at || ! $file || ! Gate::forUser($user)->allows('review', $file)) {
            return;
        }$claimed = DB::transaction(function () use ($r, $user) {
            Run::whereKey($r->id)->lockForUpdate()->first();
            if (Store::query($r)->where('kind', 'notification_claim')->where('record_key', 'ready')->exists()) {
                return false;
            }Store::add($r, 'notification_claim', 'ready', ['recipient_id' => $user->id, 'event' => 'processing_ready', 'at' => now()->toIso8601String()]);

            return true;
        });
        if (! $claimed) {
            return;
        }try {
            Mail::to($user->email)->send(new ReviewReady(url('/files/'.$file->id)));
            $status = 'submitted';
        } catch (\Throwable) {
            $status = 'delivery_unknown_manual_follow_up';
        }Store::add($r, 'notification_result', 'ready', ['recipient_id' => $user->id, 'status' => $status, 'notice' => 'No automatic retry after unknown delivery; no sensitive email payload']);
    }
}
