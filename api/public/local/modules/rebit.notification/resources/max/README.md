# Корневой сертификат для MAX

`russian_trusted_root_ca.pem` — Russian Trusted Root CA Минцифры России. Сертификат `platform-api2.max.ru`
выпущен Russian Trusted Sub CA этого центра, которого нет в стандартных наборах CA образов.

- Источник: `https://gu-st.ru/content/lending/russian_trusted_root_ca_pem.crt` (Госуслуги), скачан 25.09.2026.
- SHA-256: `D2:6D:2D:02:31:B7:C3:9F:92:CC:73:85:12:BA:54:10:35:19:E4:40:5D:68:B5:BD:70:3E:97:88:CA:8E:CF:31`.
- Действует до 27.02.2032. Цепочка MAX проверена: `openssl s_client -connect platform-api2.max.ru:443 -CAfile …` → `Verify return code: 0 (ok)`.

Используется только клиентом MAX (`CURLOPT_CAINFO`); остальные HTTPS-запросы проверяются системным набором.
Путь можно переопределить `REBIT_NOTIFICATION_MAX_CA_FILE`.
