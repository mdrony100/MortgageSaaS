<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $u, Document $d): bool
    {
        return (new LoanFilePolicy)->view($u, $d->loanFile);
    }

    public function update(User $u, Document $d): bool
    {
        return (new LoanFilePolicy)->update($u, $d->loanFile);
    }
}
