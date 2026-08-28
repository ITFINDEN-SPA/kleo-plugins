#!/usr/bin/env bash
# bootstrap-mac.sh — Onboarding automático de una Mac nueva al ecosistema Kleo
#
# Hace TODO lo necesario en una Mac nueva (MacBook Air, etc.):
#   1. Xcode Command Line Tools (git + compiladores)
#   2. Homebrew
#   3. gh (GitHub CLI)
#   4. Autenticación GitHub (cuenta itfinden)
#   5. kleo-install (harness + plugins + MCP-WHMCS)
#
# Uso (una sola línea en la Mac nueva):
#   curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/bootstrap-mac.sh -o ~/bootstrap-mac.sh && bash ~/bootstrap-mac.sh
#
# VARIABLES OPCIONALES (para automatizar la autenticación sin navegador):
#   GH_TOKEN=gho_xxxx   si se provee, se autentica con --with-token (sin diálogo)
set -euo pipefail

echo "════════════════════════════════════════════"
echo "  🚀 bootstrap-mac — Onboarding del ecosistema Kleo"
echo "════════════════════════════════════════════"

# ---------- 1. Xcode Command Line Tools ----------
echo ""
echo "== 1/5 Xcode Command Line Tools (necesarias para git/compilar) =="
if xcode-select -p >/dev/null 2>&1; then
  echo "  ✅ Ya instaladas: $(xcode-select -p)"
else
  echo "  Instalando Command Line Tools (método sin diálogo)..."
  echo "  ⚠️  Si se pide contraseña de administrador, escríbela."

  # Método 1: softwareupdate headless (no requiere diálogo GUI)
  # Evita el diálogo que a veces no aparece en sesiones remotas.
  touch /tmp/.com.apple.dt.CommandLineTools.installondemand.in-progress 2>/dev/null || true
  CLT_PRODUCT=$(softwareupdate --list 2>/dev/null | grep -i "Command Line Tools" | tail -1 | sed -E 's/^[[:space:]]*\*[[:space:]]*//' | sed 's/^Label: //')
  if [ -n "$CLT_PRODUCT" ]; then
    echo "  Producto detectado: $CLT_PRODUCT"
    sudo softwareupdate --install "$CLT_PRODUCT" --agree-to-license --verbose 2>&1 | tail -5 || true
  else
    echo "  (producto no listado por softwareupdate — usando xcode-select)"
    sudo xcode-select --install 2>&1 | head -3 || true
  fi
  rm -f /tmp/.com.apple.dt.CommandLineTools.installondemand.in-progress

  # esperar a que termine la instalación
  echo "  Esperando a que termine la instalación de CLT..."
  for i in $(seq 1 72); do
    if xcode-select -p >/dev/null 2>&1; then
      echo "  ✅ Listo: $(xcode-select -p)"
      break
    fi
    sleep 5
  done
  if ! xcode-select -p >/dev/null 2>&1; then
    echo ""
    echo "  ⚠️  Las CLT aún no están listas. Copia y ejecuta MANUALMENTE estos 2 comandos:"
    echo "      sudo xcode-select --install"
    echo "      # cuando termine (2-5 min), verifica con: xcode-select -p"
    echo "  Luego vuelve a correr: bash ~/bootstrap-mac.sh"
    exit 1
  fi
fi

# ---------- 2. Homebrew ----------
echo ""
echo "== 2/5 Homebrew =="
if command -v brew >/dev/null 2>&1 || [ -x /opt/homebrew/bin/brew ]; then
  export PATH="/opt/homebrew/bin:$PATH"
  echo "  ✅ Homebrew: $(brew --version | head -1)"
else
  echo "  Instalando Homebrew (puede pedir tu contraseña de administrador)..."
  NONINTERACTIVE=1 /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)" 2>&1 | tail -3
  export PATH="/opt/homebrew/bin:$PATH"
  echo "  ✅ Homebrew instalado"
fi

# ---------- 3. gh (GitHub CLI) ----------
echo ""
echo "== 3/5 GitHub CLI (gh) =="
if command -v gh >/dev/null 2>&1; then
  echo "  ✅ gh: $(gh --version | head -1)"
else
  echo "  Instalando gh..."
  brew install gh 2>&1 | tail -2
  echo "  ✅ gh instalado"
fi

# ---------- 4. Autenticación GitHub ----------
echo ""
echo "== 4/5 Autenticación GitHub (cuenta itfinden) =="
if gh auth status >/dev/null 2>&1; then
  echo "  ✅ Ya autenticado: $(gh api user --jq .login 2>/dev/null)"
else
  if [ -n "${GH_TOKEN:-}" ]; then
    echo "  Autenticando con GH_TOKEN (sin diálogo)..."
    echo "$GH_TOKEN" | gh auth login --with-token
    echo "  ✅ Autenticado como: $(gh api user --jq .login 2>/dev/null)"
  else
    echo "  Se abrirá la autenticación interactiva:"
    echo "    → GitHub.com → HTTPS → Login with a web browser"
    echo "    → Inicia sesión con la cuenta itfinden"
    gh auth login
  fi
  gh auth setup-git
  echo "  ✅ git configurado para usar el token (credential helper)"
fi

# ---------- 5. kleo-install ----------
echo ""
echo "== 5/5 Instalación del ecosistema =="
curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/kleo-install -o ~/kleo-install.sh
chmod +x ~/kleo-install.sh
bash ~/kleo-install.sh

echo ""
echo "════════════════════════════════════════════"
echo "  ✅ ¡Mac lista! El ecosistema Kleo está instalado."
echo "  Verifica con:"
echo "    cd ~/home/AI-STORE/KSH/KLEO_HARNESS"
echo "    plugins/kleo-funlib/kleo-funlib stats"
echo "    plugins/kleo-install/kleo-install --check"
echo "════════════════════════════════════════════"
