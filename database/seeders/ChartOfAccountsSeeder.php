<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Default chart of accounts seeded for every new user (the real template from the
 * legacy app's storage/app/private/accounts/default.json — 56 accounts).
 *
 * Tree: group accounts (is_postable=false) organize; leaf accounts (is_postable=
 * true) receive journal lines. `is_cash` (Caja/Bancos leaves) feeds the cash flow
 * and arqueo. `is_editable` locks structural edits on standard accounts (labels
 * stay editable); `is_deletable` marks the example leaves a user may remove.
 *
 * Fixed assets live under 110.x (matches LedgerService's balance-sheet split).
 * The yearly result folds into 300.2 (Cierre de ejercicio default).
 *
 * Reuse with: ChartOfAccountsSeeder::seedForUser($userId).
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * [code, name, type, is_postable, parent_code, is_cash, is_editable, is_deletable]
     */
    private const ACCOUNTS = [
        ['100', 'ACTIVOS', AccountType::Asset, false, null, false, false, false],
        ['100.1', 'EFECTIVO', AccountType::Asset, false, '100', false, false, false],
        ['100.1.1', 'DINERO EN EFECTIVO', AccountType::Asset, true, '100.1', true, true, false],
        ['100.2', 'BANCOS', AccountType::Asset, false, '100', false, false, false],
        ['100.2.1', 'DINERO EN BANCO', AccountType::Asset, true, '100.2', true, true, false],
        ['100.3', 'INVERSIONES', AccountType::Asset, false, '100', false, false, false],
        ['100.3.1', 'DINERO EN INVERSIONES', AccountType::Asset, true, '100.3', false, true, false],
        ['100.4', 'CUENTAS DE AHORRO', AccountType::Asset, false, '100', false, false, false],
        ['100.4.1', 'DINERO EN CUENTAS DE AHORRO', AccountType::Asset, true, '100.4', false, true, false],
        ['100.5', 'DEUDORES DIVERSOS', AccountType::Asset, false, '100', false, false, false],
        ['100.5.1', 'DINERO EN POSESION DE TERCEROS', AccountType::Asset, true, '100.5', false, true, false],
        ['110', 'ACTIVO FIJOS', AccountType::Asset, false, null, false, false, false],
        ['110.1', 'TERRENOS', AccountType::Asset, false, '110', false, false, false],
        ['110.1.1', 'TERRENO EQUIS', AccountType::Asset, true, '110.1', false, true, false],
        ['110.2', 'INMUEBLES CASA HABITACION / DEPTO', AccountType::Asset, false, '110', false, false, false],
        ['110.2.1', 'CASA EQUIS', AccountType::Asset, true, '110.2', false, true, false],
        ['110.3', 'EQUIPOS DE TRANSPORTE', AccountType::Asset, false, '110', false, false, false],
        ['110.3.1', 'CARRO EQUIS', AccountType::Asset, true, '110.3', false, true, false],
        ['110.4', 'MOBILIARIO Y EQUIPO', AccountType::Asset, false, '110', false, false, false],
        ['110.4.1', 'MUEBLE EQUIS', AccountType::Asset, true, '110.4', false, true, false],
        ['110.5', 'EQUIPOS DE COMPUTO', AccountType::Asset, false, '110', false, false, false],
        ['110.5.1', 'COMPUTADORA EQUIS', AccountType::Asset, true, '110.5', false, true, false],
        ['200', 'PASIVOS', AccountType::Liability, false, null, false, false, false],
        ['200.1', 'TARJETAS DE CREDITO', AccountType::Liability, false, '200', false, false, false],
        ['200.1.1', 'TARJETA DE CREDITO UNO', AccountType::Liability, true, '200.1', false, true, false],
        ['200.2', 'PRESTAMOS BANCARIOS', AccountType::Liability, false, '200', false, false, false],
        ['200.2.1', 'PRESTAMO BANCO EQUIS', AccountType::Liability, true, '200.2', false, true, false],
        ['200.3', 'CREDITOS AUTOMOTRICES', AccountType::Liability, false, '200', false, false, false],
        ['200.3.1', 'CREDITO AUTOMOTRIZ EQUIS', AccountType::Liability, true, '200.3', false, true, false],
        ['200.4', 'CREDITOS HIPOTECARIOS', AccountType::Liability, false, '200', false, false, false],
        ['200.4.1', 'CREDITO HIPOTECARIO EQUIS', AccountType::Liability, true, '200.4', false, true, false],
        ['200.5', 'ACREEDORES DIVERSOS', AccountType::Liability, false, '200', false, false, false],
        ['200.5.1', 'ACREEDOR DIVERSO EQUIS', AccountType::Liability, true, '200.5', false, true, false],
        ['300', 'PATRIMONIO', AccountType::Equity, false, null, false, false, false],
        ['300.1', 'DEFICIT O REMANENTE DEL EJERCICIO', AccountType::Equity, true, '300', false, false, false],
        ['300.2', 'DEFICIT O REMANENTE DE EJERCICIOS ANTERIORES', AccountType::Equity, true, '300', false, false, false],
        ['400', 'INGRESOS', AccountType::Income, false, null, false, false, false],
        ['400.1', 'OTROS INGRESOS', AccountType::Income, true, '400', false, true, false],
        ['400.2', 'INGRESOS EQUIS', AccountType::Income, true, '400', false, true, true],
        ['500', 'GASTOS', AccountType::Expense, false, null, false, false, false],
        ['500.1', 'GASTOS NO IDENTIFICADOS', AccountType::Expense, true, '500', false, true, false],
        ['500.2', 'OTROS GASTO', AccountType::Expense, true, '500', false, true, true],
        ['600', 'OTROS GASTOS FINANCIEROS', AccountType::Expense, false, null, false, false, false],
        ['600.1', 'INTERESES DE FINANCIAMIENTOS RECIBIDOS', AccountType::Expense, true, '600', false, false, false],
        ['600.2', 'PERDIDA CAMBIARIA', AccountType::Expense, true, '600', false, false, false],
        ['600.3', 'COMISIONES BANCARIAS', AccountType::Expense, true, '600', false, false, false],
        ['700', 'OTROS PRODUCTOS FINANCIEROS', AccountType::Income, false, null, false, false, false],
        ['700.1', 'INTERESES GANADOS EN INVERSIONES', AccountType::Income, true, '700', false, false, false],
        ['700.2', 'UTILIDAD CAMBIARIA', AccountType::Income, true, '700', false, false, false],
        ['700.3', 'OTROS PRODUCTOS FINANCIEROS', AccountType::Income, true, '700', false, false, false],
        ['800', 'OTROS GASTOS', AccountType::Expense, false, null, false, false, false],
        ['800.1', 'PERDIDA EN BAJA DE ACTIVOS FIJOS', AccountType::Expense, true, '800', false, false, false],
        ['800.2', 'OTROS GASTOS', AccountType::Expense, true, '800', false, false, false],
        ['900', 'OTROS PRODUCTOS', AccountType::Income, false, null, false, false, false],
        ['900.1', 'UTILIDAD EN VENTAS DE ACTIVOS FIJOS', AccountType::Income, true, '900', false, false, false],
        ['900.2', 'OTROS PRODUCTOS', AccountType::Income, true, '900', false, false, false],
    ];

    public function run(): void
    {
        foreach (\App\Models\User::query()->pluck('id') as $userId) {
            self::seedForUser($userId);
        }
    }

    /** Idempotent: safe to call again; existing codes are updated in place. */
    public static function seedForUser(int $userId): void
    {
        $idByCode = [];

        foreach (self::ACCOUNTS as [$code, $name, $type, $postable, $parentCode, $isCash, $isEditable, $isDeletable]) {
            $account = ChartOfAccount::updateOrCreate(
                ['user_id' => $userId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parentCode ? ($idByCode[$parentCode] ?? null) : null,
                    'is_postable' => $postable,
                    'is_active' => true,
                    'is_cash' => $isCash,
                    'is_editable' => $isEditable,
                    'is_deletable' => $isDeletable,
                ]
            );

            $idByCode[$code] = $account->id;
        }
    }
}
