<?php

/*
|--------------------------------------------------------------------------
| Taxes (IVA) configuration
|--------------------------------------------------------------------------
| Mexican IVA. Rates are keyed by a short code used on journal lines
| (tax_code). The account codes map to each user's chart of accounts:
|   - acreditable: IVA paid on purchases/expenses (asset, debit)
|   - trasladado:  IVA charged on sales/income (liability, credit)
| Adjust the codes here if a client's catálogo uses different numbers.
*/

return [
    'iva' => [
        // code => rate
        'rates' => [
            '16' => 0.16,  // tasa general
            '8' => 0.08,   // región fronteriza
            '0' => 0.00,   // tasa cero
        ],
        'accounts' => [
            'acreditable_code' => '118', // IVA Acreditable
            'trasladado_code' => '213',  // IVA Trasladado
        ],
    ],
];
