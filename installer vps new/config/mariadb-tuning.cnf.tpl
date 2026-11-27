[mysqld]
character-set-server = utf8mb4
collation-server     = utf8mb4_unicode_ci

max_connections        = 200
thread_cache_size      = 16
table_open_cache       = 4096
tmp_table_size         = 128M
max_heap_table_size    = 128M

innodb_buffer_pool_size        = {{BUFFER_POOL}}
innodb_flush_log_at_trx_commit = 2
innodb_flush_method            = O_DIRECT
innodb_file_per_table          = 1
innodb_lock_wait_timeout       = 50
innodb_io_capacity             = 2000
innodb_io_capacity_max         = 4000

slow_query_log = 1
long_query_time = 2
log_slow_verbosity = query_plan

[client]
default-character-set = utf8mb4
