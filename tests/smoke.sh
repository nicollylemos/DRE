#!/usr/bin/env bash
# Smoke test HTTP: roda no CI (contra o PHP embutido + MySQL) e depois do deploy (contra o CloudFront).
#   BASE_URL=http://localhost:8000 bash tests/smoke.sh
set -euo pipefail
BASE_URL="${BASE_URL:-http://localhost:8000}"
falhou=0
check() { # check <descrição> <caminho> <status esperado> [texto esperado]
  local body code
  body="$(curl -sS -o - -w '\n%{http_code}' "$BASE_URL$2")" || { echo "✘ $1 (sem resposta)"; falhou=1; return; }
  code="${body##*$'\n'}"; body="${body%$'\n'*}"
  if [ "$code" != "$3" ]; then echo "✘ $1 — HTTP $code (esperado $3)"; falhou=1; return; fi
  if [ -n "${4:-}" ] && ! grep -q "$4" <<<"$body"; then echo "✘ $1 — resposta sem '$4'"; falhou=1; return; fi
  echo "✔ $1"
}
check "health UP"                    /health                200 '"status":"UP"'
check "tela de login"                /login                 200 'DRE\|login\|Login'
check "API exige login"              /api/auth/me           401
check "rota inexistente"             /api/nao-existe        404
check ".env bloqueado"               /.env                  403
check "código-fonte bloqueado"       /app/bootstrap.php     403
check "dump SQL bloqueado"           /database.sql          403
exit $falhou
