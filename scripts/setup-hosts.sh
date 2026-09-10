#!/usr/bin/env bash
#
# Maps the development tenant hosts into /etc/hosts so the host-based
# tenancy (ResolveOrgFromHost) resolves locally:
#
#   http://localhost.ligacerto    -> Liga Certo
#   http://localhost.informatica  -> Informática Mais
#   http://localhost              -> estado 0 (login exclusivo do admin)
#
# Requires sudo. Run once per machine (and after Docker recreates the
# network, nothing changes — entries point at 127.0.0.1).

set -euo pipefail

HOSTS=(
    "localhost.ligacerto"
    "localhost.informatica"
)

if [[ $EUID -ne 0 ]]; then
    exec sudo "$0" "$@"
fi

changed=0
for host in "${HOSTS[@]}"; do
    if grep -Eq "^[[:space:]]*127\.0\.0\.1[[:space:]].*\b${host}\b" /etc/hosts; then
        echo "ok: ${host} já mapeado"
        continue
    fi
    echo "127.0.0.1 ${host}" >> /etc/hosts
    echo "add: 127.0.0.1 ${host}"
    changed=1
done

[[ $changed -eq 0 ]] && echo "nada a fazer" || echo "concluído"
