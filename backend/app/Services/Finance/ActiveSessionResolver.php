<?php

namespace App\Services\Finance;

use App\Models\AcademicSession;

/**
 * The academic session every finance entry is stamped with.
 *
 * The session is a property of WHEN an entry is made, never of what the
 * client asks for: the client sends no session id, and this resolver
 * decides. With no active session the ledger refuses all writes, so a
 * mis-configured school cannot bill into the void.
 */
class ActiveSessionResolver
{
    /**
     * The active academic session, or a LedgerException when none is
     * configured.
     */
    public function resolve(): AcademicSession
    {
        $session = AcademicSession::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if (! $session instanceof AcademicSession) {
            throw new LedgerException(
                'No active academic session is configured. Activate a session before recording finance entries.',
            );
        }

        return $session;
    }
}
