#!/usr/bin/env bash
#
# Instala o scaffold de língua pt_PT no projecto Laravel.
# Uso:  ./install-lang.sh /caminho/para/o/projecto
#
set -euo pipefail

PROJECT="${1:-.}"
ASSETS="$(cd "$(dirname "${BASH_SOURCE[0]}")/../assets/lang/pt_PT" && pwd)"
TARGET="$PROJECT/lang/pt_PT"

if [ ! -f "$PROJECT/artisan" ]; then
    echo "Erro: $PROJECT não parece ser um projecto Laravel (falta o artisan)." >&2
    exit 1
fi

mkdir -p "$TARGET"

for f in validation auth passwords pagination messages attributes; do
    if [ -f "$TARGET/$f.php" ]; then
        echo "  existe, ignorado: lang/pt_PT/$f.php"
    else
        cp "$ASSETS/$f.php" "$TARGET/$f.php"
        echo "  criado: lang/pt_PT/$f.php"
    fi
done

echo
echo "Ficheiros instalados. Falta configurar o .env:"
echo
echo "  APP_LOCALE=pt_PT"
echo "  APP_FALLBACK_LOCALE=en"
echo "  APP_FAKER_LOCALE=pt_PT"
echo "  APP_TIMEZONE=Africa/Maputo"
echo
echo "E limpar a cache de configuração:"
echo
echo "  php artisan config:clear"
