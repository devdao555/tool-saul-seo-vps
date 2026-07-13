CREATE TABLE IF NOT EXISTS cf_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    api_token_enc TEXT NOT NULL,
    account_id TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT NOT NULL UNIQUE,
    cf_account_id INTEGER,
    zone_id TEXT,
    status TEXT DEFAULT 'unknown',
    ns1 TEXT,
    ns2 TEXT,
    vps_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (cf_account_id) REFERENCES cf_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (vps_id) REFERENCES vps(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS vps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    ip TEXT NOT NULL,
    ssh_user TEXT NOT NULL DEFAULT 'root',
    ssh_port INTEGER NOT NULL DEFAULT 22,
    ssh_key_file TEXT NOT NULL,
    php_version TEXT NOT NULL DEFAULT '81',
    webroot_base TEXT NOT NULL DEFAULT '/www/wwwroot',
    mysql_user TEXT NOT NULL DEFAULT 'root',
    mysql_password_enc TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    module TEXT NOT NULL,
    action TEXT NOT NULL,
    target TEXT,
    status TEXT NOT NULL,
    message TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value_enc TEXT
);

CREATE INDEX IF NOT EXISTS idx_domains_domain ON domains(domain);
CREATE INDEX IF NOT EXISTS idx_logs_created_at ON system_logs(created_at);
