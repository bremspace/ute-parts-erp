SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

* * * * * root cd {{APP_DIR}} && {{PHP_BIN}} artisan schedule:run >> /var/log/ute-scheduler.log 2>&1
