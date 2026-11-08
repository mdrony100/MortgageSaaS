<?php

namespace App\Policies;

use App\Models\LoanFile;
use App\Models\User;
use App\Services\Access;

class LoanFilePolicy
{
    public function view(User $u, LoanFile $f): bool
    {
        return Access::visible($u)->whereKey($f->id)->exists();
    }

    public function update(User $u, LoanFile $f): bool
    {
        return $this->view($u, $f) && in_array(Access::role($u, $f->organization_id), ['owner', 'admin', 'underwriter', 'processor'], true);
    }

    public function upload(User $u, LoanFile $f): bool
    {
        return $this->view($u, $f) && in_array(Access::role($u, $f->organization_id), ['owner', 'admin', 'underwriter', 'processor', 'broker'], true);
    }

    public function share(User $u, LoanFile $f): bool
    {
        return $this->view($u, $f) && (Access::admin($u, $f->organization_id) || ($f->created_by === $u->id && Access::role($u, $f->organization_id) === 'underwriter'));
    }

    public function review(User $u, LoanFile $f): bool
    {
        return $this->view($u, $f) && Access::reviewer($u, $f->organization_id);
    }

    public function restricted(User $u, LoanFile $f): bool
    {
        return $this->view($u, $f) && in_array(Access::role($u, $f->organization_id), ['owner', 'admin', 'underwriter'], true);
    }
}
