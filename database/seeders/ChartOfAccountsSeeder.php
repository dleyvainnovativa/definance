<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Standard Mexican chart-of-accounts STARTER template.
 *
 * Replace with each client's real catálogo. Group accounts (is_postable=false)
 * organize the tree; only leaf accounts receive journal lines. Cash accounts
 * (is_cash=true) feed the cash flow report. Includes the `Apertura` equity
 * account and IVA accounts reserved for Phase 8.
 *
 * Reuse with: ChartOfAccountsSeeder::seedForUser($id).
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * [code, name, type, is_postable, parent_code, is_cash]
     */
    private const ACCOUNTS = [
        // 100 — Activo
        ['100', 'Activo', AccountType::Asset, false, null, false],
        ['101', 'Caja', AccountType::Asset, true, '100', true],
        ['102', 'Bancos', AccountType::Asset, true, '100', true],
        ['105', 'Clientes', AccountType::Asset, true, '100', false],
        ['118', 'IVA Acreditable', AccountType::Asset, true, '100', false],
        ['115', 'Inventario', AccountType::Asset, true, '100', false],
        ['150', 'Activo Fijo', AccountType::Asset, true, '100', false],

        // 200 — Pasivo
        ['200', 'Pasivo', AccountType::Liability, false, null, false],
        ['201', 'Proveedores', AccountType::Liability, true, '200', false],
        ['213', 'IVA Trasladado', AccountType::Liability, true, '200', false],
        ['216', 'Impuestos por Pagar', AccountType::Liability, true, '200', false],

        // 300 — Capital
        ['300', 'Capital', AccountType::Equity, false, null, false],
        ['301', 'Capital Social', AccountType::Equity, true, '300', false],
        ['305', 'Resultado del Ejercicio', AccountType::Equity, true, '300', false],
        ['399', 'Apertura / Opening Balance Equity', AccountType::Equity, true, '300', false],

        // 400 — Ingresos
        ['400', 'Ingresos', AccountType::Income, false, null, false],
        ['400.1', 'Ventas / Ingresos', AccountType::Income, true, '400', false],
        ['410', 'Otros Ingresos', AccountType::Income, true, '400', false],

        // 500 — Gastos
        ['500', 'Gastos', AccountType::Expense, false, null, false],
        ['500.1', 'Gastos Generales', AccountType::Expense, true, '500', false],
        ['510', 'Sueldos y Salarios', AccountType::Expense, true, '500', false],
        ['520', 'Costo de Ventas', AccountType::Expense, true, '500', false],
    ];

    public function run(): void
    {
        foreach (\App\Models\User::query()->pluck('id') as $userId) {
            self::seedForUser($userId);
        }
    }

    public static function seedForUser(int $userId): void
    {
        $idByCode = [];

        foreach (self::ACCOUNTS as [$code, $name, $type, $postable, $parentCode, $isCash]) {
            $account = ChartOfAccount::updateOrCreate(
                ['user_id' => $userId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parentCode ? ($idByCode[$parentCode] ?? null) : null,
                    'is_postable' => $postable,
                    'is_active' => true,
                    'is_cash' => $isCash,
                    'is_editable' => $postable,
                    'is_deletable' => false,
                ]
            );

            $idByCode[$code] = $account->id;
        }
    }
}
