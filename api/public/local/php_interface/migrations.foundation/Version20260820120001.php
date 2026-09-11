<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\HelperException;

final class Version20260820120001 extends Version
{
    private const string EVENT_NAME = 'REBIT_NOTIFICATION_LEAD';
    private const string EVENT_SUBJECT = 'Rebit — заявка с сайта от #NAME#';
    private const string SITE_ID = 's1';

    protected $author = 'claude';

    protected $description = 'Почтовое событие для резервной доставки заявок с сайта (rebit.notification)';

    /**
     * @throws HelperException
     */
    public function up(): void
    {
        $helper = $this->getHelperManager();

        $helper->Event()->saveEventType(self::EVENT_NAME, [
            'LID' => 'ru',
            'NAME' => 'Заявка с сайта (резерв Telegram)',
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
#NAME# - Имя клиента
#PHONE# - Телефон клиента
#EMAIL# - E-mail клиента
#DESCRIPTION# - Описание задачи (HTML-безопасно)
#PAGE# - Страница, с которой отправлена заявка
#FILE_NAME# - Имя приложенного файла ТЗ (пусто, если файла нет)
TEXT;
    }

    private function getHtmlMessage(): string
    {
        return <<<'HTML'
<div style="margin:0;padding:24px 16px;background-color:#f4f7fb;font-family:Arial,sans-serif;color:#17233b;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr>
            <td style="padding:24px 32px;background:#0f172a;color:#ffffff;">
                <div style="font-size:12px;line-height:18px;letter-spacing:1.6px;text-transform:uppercase;opacity:0.8;">Rebit • Заявка с сайта</div>
                <div style="margin-top:8px;font-size:22px;line-height:30px;font-weight:700;">#NAME#</div>
                <div style="margin-top:4px;font-size:13px;line-height:20px;opacity:0.8;">Telegram недоступен — доставлено резервным каналом.</div>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 8px;font-size:15px;line-height:23px;"><b>Телефон:</b> #PHONE#</p>
                <p style="margin:0 0 8px;font-size:15px;line-height:23px;"><b>E-mail:</b> #EMAIL#</p>
                <p style="margin:0 0 8px;font-size:15px;line-height:23px;"><b>Файл ТЗ:</b> #FILE_NAME#</p>
                <p style="margin:24px 0 8px;font-size:13px;line-height:20px;color:#64748b;">Описание задачи</p>
                <p style="margin:0 0 24px;font-size:15px;line-height:23px;">#DESCRIPTION#</p>
                <p style="margin:0;font-size:12px;line-height:18px;color:#94a3b8;word-break:break-all;">Страница: #PAGE#</p>
            </td>
        </tr>
    </table>
</div>
HTML;
    }
}
