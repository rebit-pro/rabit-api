<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\HelperException;

final class Version20260715120001 extends Version
{
    private const string EVENT_NAME = 'REBIT_LEADHUNTER_LEAD';
    private const string EVENT_SUBJECT = 'Rebit — заявка с #SOURCE#: #TITLE#';
    private const string SITE_ID = 's1';

    protected $author = 'claude';

    protected $description = 'Почтовое событие для резервной доставки внешних заявок (rebit.leadhunter)';

    /**
     * @throws HelperException
     */
    public function up(): void
    {
        $helper = $this->getHelperManager();

        $helper->Event()->saveEventType(self::EVENT_NAME, [
            'LID' => 'ru',
            'NAME' => 'Заявка с внешней площадки (резерв Telegram)',
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
#SOURCE# - Название площадки
#TITLE# - Заголовок заявки
#DESCRIPTION# - Описание заявки (HTML-безопасно)
#URL# - Ссылка на заявку
#KEYWORDS# - Сработавшие ключевые слова
TEXT;
    }

    private function getHtmlMessage(): string
    {
        return <<<'HTML'
<div style="margin:0;padding:24px 16px;background-color:#f4f7fb;font-family:Arial,sans-serif;color:#17233b;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr>
            <td style="padding:24px 32px;background:#0f172a;color:#ffffff;">
                <div style="font-size:12px;line-height:18px;letter-spacing:1.6px;text-transform:uppercase;opacity:0.8;">Rebit • Leadhunter</div>
                <div style="margin-top:8px;font-size:22px;line-height:30px;font-weight:700;">Заявка с #SOURCE#</div>
                <div style="margin-top:4px;font-size:13px;line-height:20px;opacity:0.8;">Telegram недоступен — доставлено резервным каналом.</div>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 8px;font-size:18px;line-height:26px;font-weight:700;">#TITLE#</p>
                <p style="margin:0 0 16px;font-size:13px;line-height:20px;color:#64748b;">Ключевые слова: #KEYWORDS#</p>
                <p style="margin:0 0 24px;font-size:15px;line-height:23px;">#DESCRIPTION#</p>
                <a href="#URL#" style="display:inline-block;padding:12px 24px;background:#1d4ed8;color:#ffffff;font-size:15px;line-height:22px;font-weight:700;text-decoration:none;border-radius:10px;">Открыть заявку</a>
                <p style="margin:24px 0 0;font-size:12px;line-height:18px;color:#94a3b8;word-break:break-all;">#URL#</p>
            </td>
        </tr>
    </table>
</div>
HTML;
    }
}
