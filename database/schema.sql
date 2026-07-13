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

CREATE TABLE IF NOT EXISTS security_scans (
    domain TEXT PRIMARY KEY,
    vps_id INTEGER,
    status TEXT NOT NULL,
    summary TEXT,
    detail TEXT,
    scanned_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (vps_id) REFERENCES vps(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS vps_health (
    vps_id INTEGER PRIMARY KEY,
    reachable INTEGER NOT NULL DEFAULT 0,
    cpu_percent INTEGER,
    ram_percent INTEGER,
    ram_used_mb INTEGER,
    ram_total_mb INTEGER,
    disk_percent INTEGER,
    disk_used_gb REAL,
    disk_total_gb REAL,
    load_avg TEXT,
    uptime TEXT,
    services TEXT,
    error TEXT,
    checked_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (vps_id) REFERENCES vps(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ssl_checks (
    domain TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    valid_to TEXT,
    days_left INTEGER,
    issuer TEXT,
    https_ok INTEGER,
    http_redirects_https INTEGER,
    error TEXT,
    checked_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_domains_domain ON domains(domain);
CREATE INDEX IF NOT EXISTS idx_logs_created_at ON system_logs(created_at);
