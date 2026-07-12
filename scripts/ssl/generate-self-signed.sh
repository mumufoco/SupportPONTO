#!/bin/bash
# =============================================================================
# Gera certificado TLS autoassinado para desenvolvimento local.
#
# INF-01 FIX: O nginx agora exige TLS. Use este script para dev local.
# Para produção, use Let's Encrypt:
#   certbot certonly --standalone -d seu-dominio.com
#   cp /etc/letsencrypt/live/seu-dominio.com/fullchain.pem docker/ssl/cert.pem
#   cp /etc/letsencrypt/live/seu-dominio.com/privkey.pem  docker/ssl/key.pem
# =============================================================================
set -e

OUTDIR="$(cd "$(dirname "$0")/../../docker/ssl" && pwd)"
mkdir -p "$OUTDIR"

echo "Gerando certificado autoassinado em $OUTDIR ..."

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout "$OUTDIR/key.pem" \
  -out    "$OUTDIR/cert.pem" \
  -subj   "/C=BR/ST=Goias/L=Goiania/O=SupportSondagens/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" 2>/dev/null

chmod 600 "$OUTDIR/key.pem"
chmod 644 "$OUTDIR/cert.pem"

echo "✅ Certificado gerado com sucesso:"
echo "   $OUTDIR/cert.pem  (certificado público)"
echo "   $OUTDIR/key.pem   (chave privada — protegida 600)"
echo ""
echo "⚠️  Este certificado é AUTOASSINADO — apenas para desenvolvimento local."
echo "   O navegador exibirá aviso de segurança (normal em dev)."
echo "   Em produção, substitua pelos certificados do Let's Encrypt ou CA válida."
