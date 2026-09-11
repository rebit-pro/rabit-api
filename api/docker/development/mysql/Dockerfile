FROM percona:8.0

USER root

COPY ./common/mysql/bitrix.cnf /etc/my.cnf.d/bitrix.cnf
RUN chmod 644 /etc/my.cnf.d/bitrix.cnf && chown root:root /etc/my.cnf.d/bitrix.cnf

USER mysql