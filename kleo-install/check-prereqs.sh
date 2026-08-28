#!/usr/bin/env bash
# check-prereqs.sh — Diagnóstico de pre-requisitos para una Mac nueva
#
# NO instala nada: solo valida cada requisito, muestra ✅/❌ y, al lado,
# el comando exacto para ejecutarlo MANUALMENTE. Al final agrupa todos
# los comandos pendientes listos para copiar/pegar en orden.
#
# Uso:
#   curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/check-prereqs.sh -o ~/check-prereqs.sh
#   bash ~/check-prereqs.sh
set -uo pipefail

PENDING=()

check() { # check "nombre" "comando_estado" "comando_instalar" "nota"
  local name="$1" test_cmd="$2" install_cmd="$3" note="${4:-}"
  if eval "$test_cmd" >/dev/null 2>&1; then
    printf "  ✅ %-28s %s\n" "$name" "${note:+($note)}"
  else
    printf "  ❌ %-28s\n" "$name"
    printf "      → instalar: %s\n" "$install_cmd"
    [ -n "$note" ] && printf "      → nota: %s\n" "$note"
    PENDING+=("$name")
  fi
}

echo "════════════════════════════════════════════════════════"
echo "  🔍 kleo check-prereqs — Diagnóstico de Mac nueva"
echo "  (solo valida; NO instala nada)"
echo "════════════════════════════════════════════════════════"
echo ""
echo "== Pre-requisitos =="

check "1. Xcode Command Line Tools" \
  "xcode-select -p" \
  "sudo xcode-select --install" \
  "descarga ~750MB en 2.a; verificar luego con: xcode-select -p"

check "2. git" \
  "command -v git" \
  "sudo xcode-select --install  (instala git junto a las CLT)" \
  "viene con las Xcode CLT"

check "3. Homebrew" \
  "command -v brew || [ -x /opt/homebrew/bin/brew ]" \
  '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"' \
  "pide contraseña de admin una vez"

check "4. gh (GitHub CLI)" \
  "command -v gh" \
  "brew install gh" \
  "requiere Homebrew (paso 3)"

check "5. Autenticación GitHub (itfinden)" \
  "gh auth status" \
  "gh auth login   → GitHub.com / HTTPS / Login with a web browser" \
  "cuenta: itfinden; luego: gh auth setup-git"

check "6. git credential helper (token HTTPS)" \
  "git config --global credential.helper 2>/dev/null | grep -qE 'gh|osxkeychain|manager'" \
  "gh auth setup-git" \
  "tras autenticar con gh"

check "7. node (≥22)" \
  "command -v node >/dev/null 2>&1 && [ \"\$(node -v | cut -d. -f1 | tr -d 'v')\" -ge 22 ]" \
  "brew install node@22 && brew link --overwrite node@22" \
  "o nvm: curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash"

check "8. pnpm" \
  "command -v pnpm" \
  "corepack enable && corepack prepare pnpm@latest --activate" \
  "requiere node (paso 7)"

check "9. Acceso red a GitHub" \
  "curl -fsSI --max-time 8 https://github.com >/dev/null" \
  "revisar conexión / VPN / proxy" \
  ""

echo ""
echo "════════════════════════════════════════════════════════"
if [ ${#PENDING[@]} -eq 0 ]; then
  echo "  🎉 ¡Todo listo! Ejecuta ahora:"
  echo "     curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/kleo-install -o ~/kleo-install.sh"
  echo "     bash ~/kleo-install.sh"
else
  echo "  ⚠️  Faltan ${#PENDING[@]} requisitos: ${PENDING[*]}"
  echo ""
  echo "  📋 COMANDOS PARA EJECUTAR A MANO (en orden):"
  echo ""
  n=1
  for name in "${PENDING[@]}"; do
    case "$name" in
      "1. Xcode Command Line Tools"|"2. git")
        echo "  [$n] sudo xcode-select --install"
        echo "      # esperar ~2-5 min; comprobar: xcode-select -p"
        ;;
      "3. Homebrew")
        echo "  [$n] /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\""
        ;;
      "4. gh (GitHub CLI)")
        echo "  [$n] brew install gh"
        ;;
      "5. Autenticación GitHub (itfinden)")
        echo "  [$n] gh auth login"
        ;;
      "6. git credential helper (token HTTPS)")
        echo "  [$n] gh auth setup-git"
        ;;
      "7. node (≥22)")
        echo "  [$n] brew install node@22 && brew link --overwrite node@22"
        ;;
      "8. pnpm")
        echo "  [$n] corepack enable && corepack prepare pnpm@latest --activate"
        ;;
      "9. Acceso red a GitHub")
        echo "  [$n] revisar conexión a internet (ping github.com)"
        ;;
    esac
    n=$((n+1))
  done
  echo ""
  echo "  ⏩ Cuando termines cada uno, vuelve a correr este check:"
  echo "     bash ~/check-prereqs.sh"
  echo "  o directamente el instalador:"
  echo "     bash ~/kleo-install.sh"
fi
echo "════════════════════════════════════════════════════════"
