#!/bin/bash

# Define the path to your database
DB_FILE="database.sqlite"

if [ ! -f "$DB_FILE" ]; then
    echo "❌ Error: $DB_FILE not found!"
    exit 1
fi

echo "🏴‍☠️ Refitting the Anchor: $DB_FILE"

# Apply the Pirate-grade optimizations
# 1. WAL: Allows concurrent readers and writers.
# 2. NORMAL Synchronous: Faster writes without risking corruption in 99% of crashes.
# 3. Busy Timeout: Tells SQLite to wait 5000ms before failing on a lock.
sqlite3 "$DB_FILE" <<EOF
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA busy_timeout = 5000;
PRAGMA mmap_size = 30000000000;
EOF

echo "✅ Success: WAL mode enabled and timeout set."
echo "🔗 You should now see $DB_FILE-shm and $DB_FILE-wal files in the directory."
