# Deploy Civin on Back4app Containers

Civin is a relational Laravel application, so the compatible Back4app product is
**Back4app Containers**. Back4app Database/Parse is not a drop-in replacement
for the application's MySQL schema, Eloquent relationships, migrations,
Sanctum, or Filament. Keep the Laravel API in a container and connect it to
managed MySQL and Redis services.

## What the Back4app image runs

`Dockerfile.back4app` is intentionally at the repository root because Back4app
builds with the repository root as its Docker context. It builds Composer
dependencies and Vite assets, then runs:

- Apache and Laravel on TCP port `8080`;
- one Laravel queue worker;
- Laravel's scheduler;
- optional locked database migrations and one-time reference-data seeders.

Supervisor keeps all three long-running processes alive because Back4app
Containers does not provide Fly-style process groups or a release command.
Application and Apache logs go to stdout/stderr for the Back4app log viewer.

## 1. Provision dependencies

Before deploying, create:

1. A publicly reachable managed MySQL 8-compatible database with TLS and
   network restrictions where the provider supports them.
2. A publicly reachable managed Redis service. Redis backs queue processing,
   cache locks, scheduler single-server locks, and deployment migration locks.
3. SMTP credentials for verification and password-reset mail.
4. Firebase and Agora credentials used by the mobile application.

Back4app Containers have an ephemeral filesystem. Civin currently stores media
URLs rather than uploaded media, so this is safe today. Do not add durable
uploads to `storage/app` later; configure S3-compatible object storage first.

## 2. Generate secrets

Generate the Laravel key locally without changing the local `.env`:

```sh
cd backend
php artisan key:generate --show
```

Convert the Firebase service account to a single-line secret:

```sh
base64 < firebase-service-account.json | tr -d '\n'
```

Add the result as `FIREBASE_CREDENTIALS_BASE64`. The entrypoint reconstructs the
file inside the container with restricted permissions. Never commit the JSON.

## 3. Create the Back4app application

1. In Back4app, create a **Container as a Service** application.
2. Connect the GitHub repository and select the deployment branch.
3. Set the Dockerfile path to `Dockerfile.back4app`.
4. Set the exposed/application port to `8080`.
5. Set the health-check path to `/up`.
6. Copy every required value from
   `backend/deploy/.env.back4app.example` into the dashboard's environment
   variables. Replace every placeholder.
7. Set `APP_URL`, `SESSION_DOMAIN`, and `SANCTUM_STATEFUL_DOMAINS` to the exact
   Back4app hostname initially. Update them after attaching a custom domain.

Do not set secrets as Docker build arguments. Civin only reads them at runtime,
so they remain out of image layers and build logs.

## 4. First deployment

For the first deployment, use:

```dotenv
BACK4APP_RUN_MIGRATIONS=true
BACK4APP_RUN_SEEDERS=true
```

The seeders add countries, global settings, and admin roles. They do not run
`DatabaseSeeder`, which would create the development admin account. After a
successful first deployment, set `BACK4APP_RUN_SEEDERS=false` and redeploy.
Keep migrations enabled for later releases.

Verify:

```sh
curl --fail https://YOUR_BACK4APP_HOST/up
curl -i https://YOUR_BACK4APP_HOST/api/v1/countries
```

Also verify registration, queued verification email, Firebase linking, the
Filament admin page, and a scheduled job.

## 5. Scaling and operations

- Use a plan with enough memory for Apache, a Laravel queue worker, and the
  scheduler. A 256 MB container is likely too small for this image; start at
  1 GB and measure.
- Every replica runs a queue worker. That is safe and increases throughput.
- Every replica starts the scheduler, but scheduled definitions use shared
  Redis locks (`onOneServer` and `withoutOverlapping`) to avoid duplicate runs.
- Migrations use Laravel's shared-cache isolation lock. Deploy only when Redis
  is healthy.
- If you intentionally run a web-only replica, set
  `BACK4APP_RUN_QUEUE_WORKER=false` and `BACK4APP_RUN_SCHEDULER=false`.
- `BROADCAST_CONNECTION=log` does not deliver real-time events. Configure a
  hosted Pusher-compatible provider before enabling production live chat.

## Troubleshooting

- **Health check failed:** confirm port `8080`, `/up`, `APP_KEY`, MySQL, and
  Redis values, then inspect deployment logs.
- **Redirects use HTTP:** confirm the request reaches Apache with
  `X-Forwarded-Proto`; Laravel trusts Back4app's proxy.
- **Migration lock error:** ensure `CACHE_STORE=redis` and Redis is reachable.
- **Firebase errors:** regenerate the base64 value without line wrapping and
  confirm the service account belongs to the mobile app's Firebase project.
- **Out of memory:** increase the container memory before reducing PHP's
  production limits or disabling required background processes.
