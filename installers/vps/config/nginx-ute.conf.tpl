# ==========================================================
# Domain ERP — HTTP (challenge ACME + redirect ke HTTPS)
# ==========================================================
server {
    listen 80;
    server_name {{SERVER_NAMES}};

    location /.well-known/acme-challenge/ {
        alias /var/www/certbot/.well-known/acme-challenge/;
        default_type "text/plain";
        add_header Cache-Control "no-store" always;
        try_files $uri =404;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# ==========================================================
# Domain ERP — HTTPS (sertifikat Let's Encrypt / self-signed)
# ==========================================================
server {
    listen 443 ssl http2;
    server_name {{SERVER_NAMES}};

    root {{APP_DIR}}/public;
    index index.php;

    ssl_certificate     {{SSL_CERT}};
    ssl_certificate_key {{SSL_KEY}};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 2100m;
    client_body_timeout 1800;
    charset utf-8;

    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript application/x-javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{{PHP_SOCKET}};
        fastcgi_read_timeout 1800;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~* \.(?:css|js|mjs|jpg|jpeg|png|gif|webp|svg|ico|woff2?|ttf|eot|map)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/ute-parts-access.log;
    error_log  /var/log/nginx/ute-parts-error.log warn;
}

# ==========================================================
# Akses via IP — HTTP (tanpa redirect, dipakai NAT port 80)
# ==========================================================
server {
    listen 80 default_server;
    server_name _;

    root {{APP_DIR}}/public;
    index index.php;

    client_max_body_size 2100m;
    client_body_timeout 1800;
    charset utf-8;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    location /.well-known/acme-challenge/ {
        alias /var/www/certbot/.well-known/acme-challenge/;
        default_type "text/plain";
        add_header Cache-Control "no-store" always;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{{PHP_SOCKET}};
        fastcgi_read_timeout 1800;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/ute-parts-ip-access.log;
    error_log  /var/log/nginx/ute-parts-ip-error.log warn;
}

# ==========================================================
# Akses via IP — HTTPS (self-signed, untuk NAT port 443)
# ==========================================================
server {
    listen 443 ssl http2 default_server;
    server_name _;

    root {{APP_DIR}}/public;
    index index.php;

    ssl_certificate     {{IP_SSL_CERT}};
    ssl_certificate_key {{IP_SSL_KEY}};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    client_max_body_size 2100m;
    client_body_timeout 1800;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{{PHP_SOCKET}};
        fastcgi_read_timeout 1800;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/ute-parts-ip-access.log;
    error_log  /var/log/nginx/ute-parts-ip-error.log warn;
}
