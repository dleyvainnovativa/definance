<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f7f2ef;font-family:-apple-system,'Segoe UI',Roboto,sans-serif;color:#17202e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f2ef;padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e4e8ee;">
                <tr><td style="padding:24px 28px;border-bottom:1px solid #e4e8ee;">
                    <span style="display:inline-block;width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#406dab,#a84882);color:#fff;text-align:center;line-height:28px;font-weight:700;">D</span>
                    <span style="font-weight:700;font-size:18px;margin-left:8px;vertical-align:middle;">DeFinance</span>
                </td></tr>
                <tr><td style="padding:28px;">
                    <h1 style="font-size:20px;margin:0 0 12px;">Bienvenido{{ $user->name ? ', '.$user->name : '' }}</h1>
                    <p style="margin:0 0 14px;line-height:1.55;color:#5a6b7d;">
                        Tu cuenta de DeFinance está lista. Ya puedes registrar pólizas, consultar tus reportes
                        (balance, resultados, flujo de efectivo) y llevar tu contabilidad con partida doble.
                    </p>
                    <p style="margin:0 0 20px;line-height:1.55;color:#5a6b7d;">
                        Inicia sesión cuando quieras para comenzar.
                    </p>
                    <p style="margin:0;color:#8795a5;font-size:13px;">
                        Si no creaste esta cuenta, puedes ignorar este correo.
                    </p>
                </td></tr>
            </table>
            <p style="color:#8795a5;font-size:12px;margin:16px 0 0;">DeFinance · Contabilidad para tu negocio</p>
        </td></tr>
    </table>
</body>
</html>
