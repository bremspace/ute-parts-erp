[www]

user = www-data
group = www-data

listen = {{PHP_SOCKET}}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = {{PHP_MAX_CHILDREN}}
pm.start_servers = {{PHP_START_SERVERS}}
pm.min_spare_servers = {{PHP_MIN_SPARE}}
pm.max_spare_servers = {{PHP_MAX_SPARE}}
pm.max_requests = 500

catch_workers_output = yes
clear_env = no

security.limit_extensions = .php
