#!/usr/bin/env bash
set -euf -o pipefail

if [[ "${MODULE_DROPBOX_DISABLED:-0}" -gt 0 ]]; then
    echo "MODULE_DROPBOX_DISABLED is disabled in your configuration file (eg. ~/.photosync)... Exiting module"
    exit 0
fi
echo "Started Dropbox archiving module"

dropbox_upload_dir=${MODULE_DROPBOX_UPLOAD_DIR:-"${HOME}/Dropbox/Camera Uploads"}
if [ ! -d "${dropbox_upload_dir}" ]; then
    echo "MODULE_DROPBOX_UPLOAD_DIR must be configured in your configuration file (eg. ~/.photosync) to purge Dropbox Camera Uploads... Exiting module"
    exit 0
fi

PICTUREDIR="$1"
if [ ! -d "${PICTUREDIR}" ]; then
    echo "First argument must be a valid folder. Exiting module"
    exit 0
fi

target_dir=${MODULE_DROPBOX_TARGET_DIR:-"Camera Uploads"}
if [ ! -d "${PICTUREDIR}/${target_dir}" ]; then
    mkdir -p "${PICTUREDIR}/${target_dir}"
fi

# Move recent pictures from Dropbox
find "${dropbox_upload_dir}/" \
    -type f \
    -maxdepth 1 \
    \( -iname '*.jpg' -o -iname '*.png' -o -iname '*.jpeg' -o -iname '*.mp4' -o -iname '*.mov' \) \
    -exec mv {} "${PICTUREDIR}/${target_dir}/" \; > /dev/null 2>&1
echo "Dropbox archived"
