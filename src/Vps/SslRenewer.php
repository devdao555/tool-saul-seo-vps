<?php

namespace App\Vps;

use App\Support\Validator;

/**
 * Best-effort SSL renewal over SSH: tries certbot first, then acme.sh (the two tools
 * aaPanel's own "Let's Encrypt" button is commonly backed by across versions). Neither
 * is guaranteed to be installed/configured for a given site — the result tells you which
 * one (if any) actually ran, so you can fall back to renewing via the aaPanel UI.
 */
class SslRenewer
{
    public function __construct(private array $vps)
    {
    }

    private function q(string $value): string
    {
        return SshClient::bashQuote($value);
    }

    public function renew(string $domain): SshResult
    {
        if (!Validator::isDomain($domain)) {
            throw new \InvalidArgumentException("Domain không hợp lệ: {$domain}");
        }
        $d = $this->q($domain);

        $script = <<<BASH
set +e
if command -v certbot >/dev/null 2>&1; then
  if certbot certificates 2>/dev/null | grep -q {$d}; then
    certbot renew --cert-name {$d} --nginx --non-interactive 2>&1
    if [ \$? -eq 0 ]; then
      echo "SAUL_RENEW_METHOD=certbot"
      echo "===SAUL_RENEW_OK==="
      exit 0
    fi
  fi
fi

if [ -x "\$HOME/.acme.sh/acme.sh" ]; then
  "\$HOME/.acme.sh/acme.sh" --renew -d {$d} --force 2>&1
  if [ \$? -eq 0 ]; then
    echo "SAUL_RENEW_METHOD=acme.sh"
    echo "===SAUL_RENEW_OK==="
    exit 0
  fi
fi

echo "SAUL_RENEW_NOT_FOUND"
exit 1
BASH;

        return SshClient::forVps($this->vps)->runScript($script, 120);
    }
}
