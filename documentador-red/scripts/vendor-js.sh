#!/usr/bin/env bash
# Descarga las librerías JS a public/js/vendor/ (ejecutar una vez, con internet) y evita depender de CDN.
set -euo pipefail
cd "$(dirname "$0")/../public/js/vendor"
curl -fsSL -o chart.umd.min.js    https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js
curl -fsSL -o cytoscape.min.js    https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.27.0/cytoscape.min.js
curl -fsSL -o qrcode-generator.js https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js
sha384sum *.js || true
echo "Listo. Con las 3 librerías locales el CSP deja de permitir CDN."
