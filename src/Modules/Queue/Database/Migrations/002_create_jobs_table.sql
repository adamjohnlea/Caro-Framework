CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue TEXT NOT NULL DEFAULT 'default',
    job_class TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'processing', 'completed', 'failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 3,
    error_message TEXT,
    available_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME
);
CREATE INDEX IF NOT EXISTS idx_jobs_status_available ON jobs(status, available_at);
CREATE INDEX IF NOT EXISTS idx_jobs_queue ON jobs(queue);
