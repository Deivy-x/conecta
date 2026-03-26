<?php
// ============================================================
// Php/mail_helper.php — Envío de correos para QuibdóConecta
// Usa Resend API (https://resend.com) — solo necesita curl
//
// Variable de entorno requerida en Railway:
//   RESEND_API_KEY = re_xxxxxxxxxxxx
//
// Remitente: configura MAIL_FROM abajo o como variable de entorno
// ============================================================

defined('MAIL_FROM')      or define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'QuibdóConecta <noreply@quibdoconecta.com>');
defined('RESEND_API_KEY') or define('RESEND_API_KEY',  getenv('RESEND_API_KEY') ?: '');
defined('BASE_URL')       or define('BASE_URL',        getenv('BASE_URL')       ?: 'https://conecta-production-818e.up.railway.app');

/**
 * Envía un correo usando Resend API.
 *
 * @param string $para      Correo destino
 * @param string $asunto    Asunto del correo
 * @param string $html      Cuerpo HTML
 * @return bool             true si se envió correctamente
 */
function enviarCorreo(string $para, string $asunto, string $html): bool
{
    $apiKey = RESEND_API_KEY;
    if (!$apiKey) {
        error_log('[QuibdóConecta Mail] RESEND_API_KEY no configurada.');
        return false;
    }

    $payload = json_encode([
        'from'    => MAIL_FROM,
        'to'      => [$para],
        'subject' => $asunto,
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 8,
    ]);

    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        return true;
    }

    error_log('[QuibdóConecta Mail] Error Resend HTTP ' . $status . ': ' . $resp);
    return false;
}

/**
 * Construye y envía la notificación de nuevo mensaje de chat.
 *
 * @param string $nombreRemitente   Nombre de quien envió el mensaje
 * @param string $correoDest        Correo del destinatario
 * @param string $nombreDest        Nombre del destinatario
 * @param string $mensajeTexto      Texto del mensaje (se muestra resumido)
 * @param int    $deUsuarioId       ID del remitente (para el enlace al chat)
 * @return bool
 */
function notificarNuevoMensaje(
    string $nombreRemitente,
    string $correoDest,
    string $nombreDest,
    string $mensajeTexto,
    int    $deUsuarioId
): bool {
    $asunto      = '💬 ' . $nombreRemitente . ' te envió un mensaje en QuibdóConecta';
    $preview     = mb_strlen($mensajeTexto) > 120
                    ? mb_substr($mensajeTexto, 0, 117) . '...'
                    : $mensajeTexto;
    $enlaceChat  = BASE_URL . '/chat.php?con=' . $deUsuarioId;
    $nombreEsc   = htmlspecialchars($nombreRemitente, ENT_QUOTES, 'UTF-8');
    $destEsc     = htmlspecialchars($nombreDest,      ENT_QUOTES, 'UTF-8');
    $previewEsc  = htmlspecialchars($preview,         ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nuevo mensaje</title>
</head>
<body style="margin:0;padding:0;background:#0a0f0a;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f0a;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="540" cellpadding="0" cellspacing="0"
               style="background:#0e1f11;border-radius:18px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:540px;width:100%;">

          <!-- Header verde/dorado/azul -->
          <tr>
            <td style="height:4px;background:linear-gradient(90deg,#27a855 33.3%,#f5c800 33.3% 66.6%,#1a56db 66.6%);"></td>
          </tr>

          <!-- Logo + título -->
          <tr>
            <td style="padding:32px 36px 0;text-align:center;">
              <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;">
                Quibdó<span style="color:#a3f0b5;">Conecta</span>
              </div>
              <div style="font-size:12px;color:rgba(255,255,255,.35);margin-top:4px;letter-spacing:1px;text-transform:uppercase;">
                🌿 La plataforma del talento chocoano
              </div>
            </td>
          </tr>

          <!-- Cuerpo -->
          <tr>
            <td style="padding:28px 36px 0;">
              <p style="margin:0 0 6px;font-size:15px;color:rgba(255,255,255,.55);">
                Hola, <strong style="color:#fff;">{$destEsc}</strong>
              </p>
              <p style="margin:0 0 22px;font-size:15px;color:rgba(255,255,255,.55);line-height:1.6;">
                <strong style="color:#5dd882;">{$nombreEsc}</strong>
                te ha enviado un mensaje en QuibdóConecta:
              </p>

              <!-- Burbuja del mensaje -->
              <div style="background:rgba(255,255,255,.05);border-left:3px solid #27a855;border-radius:0 12px 12px 0;padding:16px 20px;margin-bottom:28px;">
                <p style="margin:0;font-size:14px;color:rgba(255,255,255,.75);line-height:1.65;font-style:italic;">
                  &ldquo;{$previewEsc}&rdquo;
                </p>
              </div>

              <!-- CTA -->
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding-bottom:28px;">
                    <a href="{$enlaceChat}"
                       style="display:inline-block;background:linear-gradient(135deg,#0a4020,#27a855);color:#fff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 32px;border-radius:30px;letter-spacing:.2px;box-shadow:0 6px 20px rgba(39,168,85,.4);">
                      💬 Responder mensaje
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 36px 28px;border-top:1px solid rgba(255,255,255,.06);">
              <p style="margin:0;font-size:12px;color:rgba(255,255,255,.25);text-align:center;line-height:1.7;">
                Recibiste este correo porque tienes una cuenta en QuibdóConecta.<br>
                Si no quieres recibir estas notificaciones, puedes configurarlo en tu perfil.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    return enviarCorreo($correoDest, $asunto, $html);
}
