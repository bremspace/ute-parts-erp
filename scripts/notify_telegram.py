#!/usr/bin/env python3
import sys
import os
import urllib.request
import urllib.parse
import json
from pathlib import Path

def get_telegram_token():
    # Priority: /root/.hermes/.env or repo .env
    candidates = [Path('/root/.hermes/.env'), Path('/var/www/ute-parts-erp/.env')]
    for path in candidates:
        if path.exists():
            for line in path.read_text(encoding='utf-8', errors='ignore').splitlines():
                if line.strip().startswith('TELEGRAM_BOT_TOKEN='):
                    return line.split('=', 1)[1].strip().strip('\"\'')
    return os.environ.get('TELEGRAM_BOT_TOKEN', '')

def send_telegram(message, chat_id='1418634935', parse_mode='Markdown'):
    token = get_telegram_token()
    if not token:
        print("ERROR: TELEGRAM_BOT_TOKEN not found.", file=sys.stderr)
        return False

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    payload = {
        'chat_id': chat_id,
        'text': message,
        'parse_mode': parse_mode,
        'disable_web_page_preview': True
    }
    data = urllib.parse.urlencode(payload).encode('utf-8')
    req = urllib.request.Request(url, data=data, method='POST')

    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            res = json.loads(response.read().decode('utf-8'))
            return res.get('ok', False)
    except Exception as e:
        # Fallback without markdown if markdown parsing failed
        if parse_mode:
            return send_telegram(message, chat_id=chat_id, parse_mode='')
        print(f"ERROR sending telegram message: {e}", file=sys.stderr)
        return False

if __name__ == '__main__':
    msg = sys.argv[1] if len(sys.argv) > 1 else sys.stdin.read()
    if not msg.strip():
        print("Usage: notify_telegram.py <message>", file=sys.stderr)
        sys.exit(1)
    
    target_chat = sys.argv[2] if len(sys.argv) > 2 else '1418634935'
    success = send_telegram(msg, chat_id=target_chat)
    if success:
        print("Telegram notification sent successfully.")
        sys.exit(0)
    else:
        print("Failed to send telegram notification.", file=sys.stderr)
        sys.exit(1)
