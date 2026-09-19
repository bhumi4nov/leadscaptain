# LeadsCaptain Lead Importer

Laravel project for importing leads from the LeadsCaptain API.

The project handles API pagination, queued page processing, Redis rate limiting, API retries, idempotent database updates, and import failure handling.

## Requirements

- PHP 8.4+
- Laravel 12+
- MySQL 8+
- Redis 7+
- Composer
- Docker / Docker Compose

## Architecture

The project follows an Onion/DDD-oriented structure with separate Domain, Application, Infrastructure and Queue layers.

```text
app/
├── Domain/
│   └── Lead/
│       ├── Lead.php
│       ├── LeadDTO.php
│       └── LeadRepository.php
│
├── Application/
│   └── Lead/
│       ├── ImportLeadPage.php
│       ├── ImportAllLeads.php
│       ├── LeadPageDTO.php
│       └── HandleLeadImportFailure.php
│
├── Infrastructure/
│   └── Leadscaptain/
│       ├── LeadscaptainClient.php
│       ├── LeadscaptainRateLimiter.php
│       ├── LeadscaptainRetryPolicy.php
│       └── DatabaseLeadRepository.php
│
├── Jobs/
│   ├── FetchLeadPageJob.php
│   └── ImportAllLeadsJob.php
│
├── Events/
│   └── LeadImported.php
│
└── Notifications/
    └── LeadImportFailedNotification.php
```

The Domain layer contains the Lead entity, DTO and repository contract.

The Application layer contains the import use cases and pagination orchestration.

The Infrastructure layer contains the LeadsCaptain API client, Redis rate limiter, retry policy and database repository.

Queue jobs are used to process individual API pages asynchronously.

## LeadsCaptain API

The integration uses the LeadsCaptain leads endpoint:

```text
GET /leads
```

The first request uses:

```text
page=1
limit=100
```

Page 1 is fetched first because the API response contains the pagination information required to determine the remaining pages.

Expected pagination response:

```json
{
    "data": [],
    "page": 1,
    "limit": 100,
    "total": 0,
    "total_pages": 0
}
```

Each lead is mapped into a typed `LeadDTO` before being converted into the domain `Lead` entity.

## Configuration

The LeadsCaptain integration is configured through environment variables.

Add the following values to `.env`:

```env
LEADSCAPTAIN_API_KEY=
LEADSCAPTAIN_BASE_URL=https://api.leadscaptain.com
LEADSCAPTAIN_TIMEOUT=30
LEADSCAPTAIN_RETRY_TIMES=3
```

The application configuration is stored in:

```text
config/leadscaptain.php
```

The API key is provided through the environment and must not be committed to Git.

## Authentication

The current client sends the API key using:

```text
X-API-Key
```

The authentication header should be confirmed against the actual LeadsCaptain API credentials before running a live import.

## Pagination and Queue

The import flow is:

```text
Import command
      |
      v
Fetch page 1
      |
      v
Read total_pages
      |
      v
Dispatch page 2..N
      |
      v
Queue workers
      |
      v
Import and save leads
```

Page 1 is processed first so the application can determine how many pages are available.

Pages 2 through the final page are dispatched as individual queue jobs. Each page import uses the client's bounded asynchronous HTTP-pool path, while Horizon runs multiple page jobs concurrently.

The configured concurrency is 10 by default and can be changed through `LEADSCAPTAIN_CONCURRENCY`.

## Rate Limiting

Redis is used to enforce the LeadsCaptain request limit.

The current limiter allows:

```text
60 requests per rolling 60-second window
```

Request timestamps are stored in Redis.

An atomic Redis Lua script is used to coordinate concurrent workers and keep the request rate within the configured limit.

The limiter removes expired request timestamps, checks the current request count, allows the request when capacity is available, and waits when the limit has been reached.

## Retry

Transient failures are retried.

The retry policy handles:

- HTTP 429
- HTTP 5xx
- Request and connection failures

The configured backoff delays are:

```text
1 second
5 seconds
30 seconds
```

For HTTP 429 responses, the `Retry-After` value can be used when provided by the API.

Permanent client errors are not retried.

Retry activity is written to the dedicated LeadsCaptain log channel.

## Database

Leads are stored in the `leads` table.

The `leadscaptain_id` column is unique.

Persistence uses Laravel's `updateOrCreate`.

This prevents duplicate records when the same page is processed again or a queue job is retried.

The import is therefore idempotent for an existing LeadsCaptain lead identifier.

## Failure Handling

When an import batch fails, the failure handler records the batch ID and error information in the dedicated LeadsCaptain log.

An optional email notification can be configured using:

```env
LEADSCAPTAIN_FAILURE_NOTIFICATION_EMAIL=
```

If the email configuration is not provided, the failure is still logged.

## Docker

The project includes Docker Compose configuration for the application, MySQL, Redis and Horizon.

Build the Docker image:

```bash
docker compose build
```

Start the services:

```bash
docker compose up -d
```

The Docker environment contains:

```text
app
mysql
redis
horizon
```

The application is exposed on:

```text
http://localhost:8080
```

MySQL is exposed on:

```text
localhost:3307
```

Redis is exposed on:

```text
localhost:6380
```

Run database migrations:

```bash
docker compose exec app php artisan migrate
```

## Run Import

Start the LeadsCaptain import using the Artisan command:

```bash
docker compose exec app php artisan leadscaptain:import
```

The command starts the complete import workflow.

Horizon is included as a Docker service and can be started with:

```bash
docker compose up -d horizon
```

Horizon processes the queued page jobs.

## Testing

Run the complete test suite:

```bash
docker compose exec app php artisan test
```

Run tests with the required coverage:

```bash
docker compose exec app php artisan test --coverage --min=90
```

Latest verified test run:

```text
35 tests passed
65 assertions
```

CI enforces the assignment's 90% minimum coverage with `php artisan test --coverage --min=90`.

The test suite covers:

- API response and pagination mapping
- Lead DTO mapping
- Database persistence
- Idempotent updates
- HTTP 429 handling
- HTTP 5xx retries
- Permanent client errors
- Retry policy and backoff
- Redis rate limiting
- Queue jobs
- Pagination orchestration
- Import failure handling
- Failure notifications
- Horizon concurrency configuration

## Static Analysis

### PHPStan

Run PHPStan with:

```bash
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
```

Current result:

```text
[OK] No errors
```

### Psalm

Run Psalm with:

```bash
docker compose exec app vendor/bin/psalm --no-cache
```

Current result:

```text
No errors found!
```

Psalm currently reports 99.0291% type inference for the codebase.

## Logs

LeadsCaptain-specific logs are stored in:

```text
storage/logs/leadscaptain-YYYY-MM-DD.log
```

The dedicated `leadscaptain` channel writes to stderr so the logs are visible in Docker and container orchestration environments.

## Security

- API credentials are stored in environment variables.
- API secrets must not be committed to Git.
- Production credentials should be provided through the deployment environment.
- The actual authentication header should be verified before a live API import.

## Quality Checks

Before submitting the project, run the following checks:

```bash
docker compose exec app php artisan test --coverage --min=90

docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M

docker compose exec app vendor/bin/psalm --no-cache
```

Latest verified static checks:

```text
Tests:       35 passed
Assertions:  65
PHPStan:     No errors
Psalm:       No errors found
```

The CI workflow also runs Laravel Pint and builds the Docker image.

## Environment Setup

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

For Docker, configure the database:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=leadscaptain
DB_USERNAME=leadscaptain
DB_PASSWORD=secret
```

Configure Redis:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
```

Configure the LeadsCaptain API:

```env
LEADSCAPTAIN_API_KEY=
LEADSCAPTAIN_BASE_URL=https://api.leadscaptain.com
LEADSCAPTAIN_TIMEOUT=30
LEADSCAPTAIN_RETRY_TIMES=3
```

Start the Docker environment:

```bash
docker compose up -d
```

Run migrations:

```bash
docker compose exec app php artisan migrate
```

## Project Flow

The complete import process can be summarized as:

```text
Artisan Command
      |
      v
ImportAllLeads
      |
      v
Fetch Page 1
      |
      v
Validate Response
      |
      v
Save Page 1
      |
      v
Read total_pages
      |
      v
Dispatch Page 2..N
      |
      v
Redis Rate Limiter
      |
      v
LeadsCaptain API
      |
      v
Map LeadDTO
      |
      v
Domain Lead
      |
      v
Database upsert
```

## Security and Reliability

The implementation is designed so that:

- API credentials remain outside the source code.
- API requests are rate limited through Redis.
- Transient API failures are retried with backoff.
- Queue retries do not create duplicate database records.
- Multiple workers can process different pages concurrently.
- Import failures are logged and can trigger email notification.

## Submission Checklist

Before submitting the project, verify:

```text
[ ] Docker build works
[ ] Docker services start successfully
[ ] Database migrations run successfully
[ ] Redis is available
[ ] Horizon starts successfully
[ ] Lead import command runs
[ ] Tests pass
[ ] Coverage is at least 90%
[ ] PHPStan passes
[ ] Psalm passes
[ ] API key is not committed
[ ] README is up to date
```


## Live API Proof

The final review requires a live Leadscaptain API key. After the key is supplied, configure it only in `.env`, run the import through the queued command, verify Horizon processing and confirm the imported records in the `leads` table.

No API secret is stored in the repository.

## Deployment

The repository contains a Docker image and CI build/test/static-analysis pipeline. A production deployment target and credentials were not specified in the technical-test brief, so the deploy step must be connected to the employer's target environment rather than inventing a provider-specific deployment.
