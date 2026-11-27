[program:ute-queue]
process_name=%(program_name)s_%(process_num)02d
command={{PHP_BIN}} {{APP_DIR}}/artisan queue:work redis --queue=default --tries=3 --sleep=3 --timeout=120
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/ute-queue.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5

[group:ute]
programs=ute-queue
