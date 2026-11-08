<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Access;

class DocumentPolicy
{
    public function view(User $u, Document $d): bool
    {
        return (new LoanFilePolicy)->view($u, $d->loanFile) && (! Access::broker($u, $d->organization_id) || $d->versions()->where('uploaded_by', $u->id)->exists());
    }

    public function update(User $u, Document $d): bool
    {
        return (new LoanFilePolicy)->update($u, $d->loanFile);
    }
}
