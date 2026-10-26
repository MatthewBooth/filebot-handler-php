#!/bin/sh

if [ -f "$FILEBOT_LICENCE_FILE" ]; then
    echo "Installing FileBot license..."
    filebot --license "$FILEBOT_LICENCE_FILE"
fi

if [ -n "$OPENSUBTITLES_USERNAME" ] && [ -n "$OPENSUBTITLES_PASSWORD" ]; then
  echo "Configuring OpenSubtitles credentials for FileBot..."
  filebot -script fn:configure --def osdbUser="$OPENSUBTITLES_USERNAME" --def osdbPwd="$OPENSUBTITLES_PASSWORD" || true
elif [ -n "$OPENSUBTITLES_USERNAME" ] || [ -n "$OPENSUBTITLES_PASSWORD" ]; then
  echo "OpenSubtitles credentials not configured: both username and password must be set."
fi

exec php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
