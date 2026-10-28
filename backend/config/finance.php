<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duplicate Fee Invoices
    |--------------------------------------------------------------------------
    |
    | What raising a second fee invoice for the same student, fee
    | structure and active session does. "Warn" (the default) lets it
    | happen but requires the client to confirm; "block" refuses it
    | outright; "allow" raises it silently. Reversing the first invoice
    | clears the duplicate state either way.
    |
    */

    'duplicate_fee_invoice' => env('FINANCE_DUPLICATE_FEE_INVOICE', 'warn'),

];
