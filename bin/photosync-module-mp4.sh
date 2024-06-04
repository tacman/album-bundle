#!/usr/bin/env bash
set -euf -o pipefail

if [[ "${MODULE_MP4_DISABLED:-0}" -gt 0 ]]; then
    echo "MODULE_MP4_DISABLED is disabled in your configuration file (eg. ~/.photosync)... Exiting module"
    exit 0
fi
echo "Started WEBM encoding module"

# Arguments
PICTUREDIR="${1:-}"
MAXDEPTH="${2:-2}"

if [[ -z "$PICTUREDIR" ]] || [[ ! -d "$PICTUREDIR" ]]; then
  echo "1st argument must be a valid directory"
  exit 1
fi

if ! (ffmpeg -version &> /dev/null); then
  echo "FFmpeg must be installed on the system to use module ${BASH_SOURCE[0]}"
  exit 0
fi

getMovieDuration() {
  set +e
  duration=$(
    ffmpeg -i "${file}" -hide_banner 2>&1 \
      | awk '/Duration/{sub(".[0-9]+,", "", $0); print $2}' \
      | awk -F ':' '{print $1*3600+$2*60+$3}'
  )
  set -e

  if [[ "${?}" -gt 0 ]]; then
    echo "0"
  else
    echo "${duration}"
  fi
}

preparePreview () {
  file="${1}"

  if [ ! -f "${file}" ]; then
    echo "--: ${file} is not a file"
    return
  fi

  dir=$(dirname "${file}")
  base=$(basename "${file}")
  mkdir -p "${dir}/.preview"

  if [ ! -f "${dir}/.preview/${base}.mp4" ]; then
    echo "Encoding ${file} for web"
    ffmpeg -i "${file}" -vf "scale='min(1080,iw)':-2" -c:v libx264 -b:v 600k -c:a aac "${dir}/.preview/${base}.mp4"
  fi

  if [ ! -f "${dir}/.preview/crop/200/200/${base}.jpg" ]; then
    echo "Encoding ${file} for thumbnail"
    duration=$(getMovieDuration "${file}")
    preview_time=$(( duration / 2 ))
    echo "Taking preview at ${preview_time} seconds"
    mkdir -p "${dir}/.preview/crop/200/200/"

    # Letter-boxed preview: cf https://trac.ffmpeg.org/wiki/Scaling
    ffmpeg -i "${file}" \
      -vcodec mjpeg \
      -vframes 1 \
      -an -f rawvideo \
      -ss "${preview_time}" \
      -vf "scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2" \
      "${dir}/.preview/crop/200/200/${base}.jpg"
  fi
}

while read -r file; do
  echo "Preparing preview for '${file}'"
  preparePreview "${file}" || echo "Failed preparing preview for ${file}"
done < <( find "${PICTUREDIR}" -maxdepth "${MAXDEPTH}" \( -iname '*.mp4' -o -iname '*.mov' -o -iname '*.mkv' \) )

echo "${BASH_SOURCE[0]}: done"
