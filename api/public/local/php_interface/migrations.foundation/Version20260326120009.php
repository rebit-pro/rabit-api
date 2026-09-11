<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\HelperException;

final class Version20260326120009 extends Version
{
    private const string EVENT_NAME = 'REBIT_AUTH_REGISTRATION_CONFIRMATION';
    private const string EVENT_SUBJECT = 'RaBit API — код подтверждения регистрации';
    private const string SITE_ID = 's1';

    protected $author = 'copilot';

    protected $description = 'Почтовое событие и HTML-шаблон письма для подтверждения регистрации';

    /**
     * @throws HelperException
     */
    public function up(): void
    {
        $helper = $this->getHelperManager();

        $helper->Event()->saveEventType(self::EVENT_NAME, [
            'LID' => 'ru',
            'NAME' => 'Подтверждение регистрации RaBit API',
            'DESCRIPTION' => $this->getEventDescription(),
        ]);

        $helper->Event()->saveEventMessage(self::EVENT_NAME, [
            'ACTIVE' => 'Y',
            'LID' => self::SITE_ID,
            'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO' => '#EMAIL_TO#',
            'SUBJECT' => self::EVENT_SUBJECT,
            'BODY_TYPE' => 'html',
            'MESSAGE' => $this->getHtmlMessage(),
        ]);
    }

    /**
     * @throws HelperException
     */
    public function down(): void
    {
        $helper = $this->getHelperManager();

        $helper->Event()->deleteEventMessage([
            'EVENT_NAME' => self::EVENT_NAME,
            'SUBJECT' => self::EVENT_SUBJECT,
        ]);

        $helper->Event()->deleteEventType([
            'EVENT_NAME' => self::EVENT_NAME,
            'LID' => 'ru',
        ]);
    }

    private function getEventDescription(): string
    {
        return <<<'TEXT'
#EMAIL_TO# - E-mail получателя
#CONFIRMATION_CODE# - 6-значный код подтверждения
#EXPIRES_AT# - Время окончания действия кода
TEXT;
    }

    private function getHtmlMessage(): string
    {
        return <<<'HTML'
<div style="margin:0;padding:32px 16px;background-color:#f4f7fb;font-family:Arial,sans-serif;color:#17233b;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 8px 30px rgba(18,38,63,0.08);">
        <tr>
            <td style="padding:32px 40px;background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%);color:#ffffff;">
                <div style="font-size:12px;line-height:18px;letter-spacing:1.6px;text-transform:uppercase;opacity:0.8;">RaBit API</div>
                <div style="margin-top:8px;font-size:28px;line-height:36px;font-weight:700;">Подтверждение регистрации</div>
                <div style="margin-top:8px;font-size:15px;line-height:24px;opacity:0.9;">Введите код ниже, чтобы завершить создание аккаунта.</div>
            </td>
        </tr>
        <tr>
            <td style="padding:40px;">
                <p style="margin:0 0 16px;font-size:16px;line-height:24px;">Здравствуйте!</p>
                <p style="margin:0 0 24px;font-size:16px;line-height:24px;">
                    Мы получили запрос на регистрацию аккаунта в <strong>RaBit API</strong> для адреса <strong>#EMAIL_TO#</strong>.
                </p>
                <div style="margin:0 0 24px;padding:24px;background:#f8fafc;border:1px solid #dbe6f3;border-radius:16px;text-align:center;">
                    <div style="font-size:13px;line-height:20px;color:#64748b;text-transform:uppercase;letter-spacing:1.4px;">Код подтверждения</div>
                    <div style="margin-top:12px;font-size:36px;line-height:44px;font-weight:700;letter-spacing:8px;color:#0f172a;">#CONFIRMATION_CODE#</div>
                </div>
                <p style="margin:0 0 12px;font-size:16px;line-height:24px;">
                    Код действует до <strong>#EXPIRES_AT#</strong>.
                </p>
                <p style="margin:0;font-size:16px;line-height:24px;">
                    Если вы не запрашивали регистрацию, просто проигнорируйте это письмо.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 40px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:20px;color:#64748b;">
                Это автоматическое письмо. Пожалуйста, не отвечайте на него.
            </td>
        </tr>
    </table>
</div>
HTML;
    }
}
