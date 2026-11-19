#!/bin/sh
# Installs headless Chromium for automation/mobile-audit.py in a fresh Debian sandbox (no root needed).
set -e
pip install -q playwright >/dev/null 2>&1 || true
python3 -m playwright install chromium-headless-shell >/dev/null 2>&1 || python3 -m playwright install chromium >/dev/null 2>&1
mkdir -p /tmp/libs && cd /tmp/libs
apt-get download libatk1.0-0t64 libatk-bridge2.0-0t64 libxdamage1 libatspi2.0-0t64 >/dev/null 2>&1 || apt-get download libatk1.0-0 libatk-bridge2.0-0 libxdamage1 libatspi2.0-0 >/dev/null 2>&1 || true
for d in *.deb; do [ -f "$d" ] && dpkg -x "$d" /tmp/libs/root; done
echo "export LD_LIBRARY_PATH=/tmp/libs/root/usr/lib/x86_64-linux-gnu"
