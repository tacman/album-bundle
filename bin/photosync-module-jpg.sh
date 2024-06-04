#!/usr/bin/env bash
set -euf -o pipefail

if [[ "${MODULE_JPG_DISABLED:-0}" -gt 0 ]]; then
    echo "MODULE_JPG_DISABLED is disabled in your configuration file (eg. ~/.photosync)... Exiting module"
    exit 0
fi
echo "Started JPEG encoding module"

# Arguments
PICTUREDIR="${1:-}"
MAXDEPTH="${2:-2}"

if [[ -z "${PICTUREDIR}" ]] || [[ ! -d "${PICTUREDIR}" ]]; then
  echo "1st argument must be a valid directory"
  exit 1
fi

if ! (convert --version &> /dev/null); then
  echo "ImageMagick must be installed on the system to use module ${BASH_SOURCE[0]}"
  exit 0
fi

preparePreview () {
  file="$1"

  dir=$(dirname "${file}")
  base=$(basename "${file}")

  # Generate fitted 1024x780 image (roughly)
  preview_dir="${dir}/.preview/fit/2048/1560"
  target="${preview_dir}/${base}.jpg"
  if [ ! -f "${target}" ]; then
    mkdir -p "${preview_dir}"
    echo "Encoding ${file} to ${target}"
    convert "${file}" \
        -resize 2048x2048^ \
        -gravity Center  \
        -quality 65 \
        -auto-orient \
        "${target}"
  fi

  # Generate cropped 400x400 to fit in the 200x200 size (better for retina displays)
  preview_dir="${dir}/.preview/crop/200/200"
  target="${preview_dir}/$base.jpg"
  if [ ! -f "${target}" ]; then
    mkdir -p "${preview_dir}"
    echo "Encoding ${file} to ${target}"
    convert "${file}" \
        -resize 400x400^ \
        -gravity Center  \
        -extent 400x400  \
        -quality 30 \
        -auto-orient \
        "${target}"
  fi
}

while read -r file; do
  preparePreview "${file}"
done < <( find "${PICTUREDIR}" -maxdepth "${MAXDEPTH}" -type f -size +0 \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.heic' \) )
