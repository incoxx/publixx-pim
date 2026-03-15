# Cron Jobs & Scheduling

anyPIM uses Laravel's task scheduler to automate recurring background processes. A single system cron entry delegates all scheduling to the application.

## Setup

Add this cron entry to your server (e.g. via `crontab -e`):

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

This runs the scheduler every minute. Laravel then decides which commands are due based on their configured frequency.

::: warning
Without this cron entry, **no scheduled tasks will execute** — export jobs won't run, product versions won't publish, and calendar actions won't fire.
:::

## Scheduled Commands

The following commands are registered in `routes/console.php`:

### Horizon Snapshots

```
horizon:snapshot — every 5 minutes
```

Captures queue metrics (jobs processed, wait times, throughput) for the Horizon monitoring dashboard. This data powers the queue health graphs.

### Queue Batch Pruning

```
queue:prune-batches --hours=48 — daily
```

Cleans up completed job batch records older than 48 hours from the database, preventing the `job_batches` table from growing indefinitely.

### Scheduled Version Activation

```
versions:activate-scheduled — every minute
```

Checks for product versions with `status = scheduled` and a `publish_at` timestamp that has passed. When found, the version is activated automatically:

- The product's attribute values are replaced with the version's snapshot
- The version status changes from `scheduled` to `active`
- The previous active version is archived

This powers the **product versioning** feature where editors schedule content changes for a future date (e.g. "publish new description on March 1st").

**Related UI:** Product Editor → Versions tab → "Schedule for" date picker

### Scheduled Export Jobs

```
pim:export-job --run-scheduled — every minute
```

Finds active export jobs with a cron expression whose `next_run_at` has passed and executes them:

1. Queries products based on the job's filters and search profile
2. Generates the output file (CSV, JSON, XML, or Excel)
3. Delivers the file via the configured method (SFTP, webhook, email, or download)
4. Updates `last_run_at`, `last_status`, and calculates `next_run_at`
5. Prunes old execution logs (keeps the most recent 50 per job)

**Cron expression examples:**

| Expression | Meaning |
|-----------|---------|
| `0 0 * * *` | Daily at midnight |
| `0 */6 * * *` | Every 6 hours |
| `0 2 * * 1` | Every Monday at 02:00 |
| `30 8 1 * *` | 1st of each month at 08:30 |
| `0 0 * * 1-5` | Weekdays at midnight |

**Related UI:** Export Jobs → Create/Edit → "Schedule" section with cron expression input

### Scheduled Actions

```
actions:process-scheduled — every minute
```

Processes pending scheduled actions from the planning calendar. Each action has a `scheduled_at` timestamp and an `action_type` that determines what happens:

| Action Type | Effect |
|------------|--------|
| `activate_product` | Sets product status to `active` |
| `deactivate_product` | Sets product status to `inactive` |
| `price_change` | Updates product prices from the payload |
| `data_change` | Updates product attribute values |
| `bulk_update` | Applies changes to multiple products |
| `export` | Triggers an export job |
| `import` | Triggers an import job |
| `version_publish` | Publishes a specific product version |

After execution, the action status changes from `pending` to `completed` (or `failed` with an error message).

**Related UI:** Planning Calendar → click on a date → "New Action" dialog

### TMS Ingestion

```
tms:ingest — daily
```

Sends new or updated translatable content from the PIM to the Translation Memory Service (TMS). Only runs when `TMS_ENABLED=true`.

### TMS Sync

```
tms:sync — daily at 04:00
```

Pulls completed translations back from the TMS into the PIM database. Runs at 04:00 to avoid interfering with peak-hour editing. Only active when `TMS_ENABLED=true`.

## Calendar Integration

All scheduled activities are visualized in the **Planning Calendar** (`/calendar`), which aggregates four event sources:

1. **Scheduled Actions** — user-created timed events (color-coded by type)
2. **Export Jobs** — recurring export runs calculated from cron expressions
3. **Product Version Releases** — versions scheduled for future activation
4. **Project Deadlines** — start and end dates of active projects

The calendar supports month, week, day, and timeline views. Pending scheduled actions can be rescheduled via drag-and-drop.

## Monitoring

### Checking Scheduler Health

Verify the scheduler is running:

```bash
# Check if the cron is registered
crontab -l | grep schedule:run

# View upcoming scheduled commands
php artisan schedule:list

# Run the scheduler manually (for testing)
php artisan schedule:run
```

### Running Commands Manually

Any scheduled command can be executed on demand:

```bash
# Run all due export jobs now
php artisan pim:export-job --run-scheduled

# List all configured export jobs
php artisan pim:export-job --list

# Activate any due product versions
php artisan versions:activate-scheduled

# Process pending calendar actions
php artisan actions:process-scheduled
```

### Queue Workers

The scheduler dispatches commands, but background jobs require **queue workers**. anyPIM uses Laravel Horizon to manage workers:

```bash
# Start Horizon (recommended for production)
php artisan horizon

# Or start a basic worker
php artisan queue:work redis --tries=3 --timeout=300
```

For production, configure Horizon as a system service via Supervisor:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /path-to-your-project/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/horizon.log
stopwaitsecs=3600
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Export jobs never run | Missing cron entry | Add `schedule:run` to crontab |
| Versions don't activate | Queue worker not running | Start Horizon or a queue worker |
| TMS translations not syncing | `TMS_ENABLED=false` | Set `TMS_ENABLED=true` in `.env` |
| Actions stuck as "pending" | Scheduler not running | Check crontab and `schedule:list` |
| Horizon dashboard empty | No snapshots | Ensure `horizon:snapshot` is in schedule |
