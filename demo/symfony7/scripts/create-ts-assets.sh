#!/bin/sh
# Copy TypeScript assets from ts-assets-template to assets/ and remove old .js if any
# Run from demo/symfony7. Uses sudo if cp fails (e.g. assets/ owned by root from Docker).
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"
mkdir -p assets/controllers
do_cp() {
  cp ts-assets-template/app.ts ts-assets-template/bootstrap.ts assets/ && \
  cp ts-assets-template/controllers/hello_controller.ts ts-assets-template/controllers/csrf_protection_controller.ts assets/controllers/
}
if ! do_cp 2>/dev/null; then
  echo "Permission denied, trying with sudo..."
  if sudo cp ts-assets-template/app.ts ts-assets-template/bootstrap.ts assets/ && \
     sudo cp ts-assets-template/controllers/hello_controller.ts ts-assets-template/controllers/csrf_protection_controller.ts assets/controllers/; then
    sudo chown -R "$(whoami)" assets
    echo "Copied and set ownership to $(whoami)."
  else
    echo "Failed. Run: sudo chown -R \$(whoami) assets && make ts-assets"
    exit 1
  fi
fi
rm -f assets/app.js assets/stimulus_bootstrap.js \
  assets/controllers/hello_controller.js assets/controllers/csrf_protection_controller.js 2>/dev/null || true
echo "Done: TS assets in place."
