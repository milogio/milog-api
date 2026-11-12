# API deployment on a FullHost Value 4 VM

This runbook is for the Ubuntu Server 26.04 LTS API VM (2 CPU cores, 4 GB RAM,
50 GB disk). The Next.js UI is deployed on a separate VM. The only public API
hostname is `api.milog.ca`. Use `docker-compose.production.yml`; the default
Compose file is for local development.

## Deployment inputs and prerequisites

- Point `api.milog.ca` at the API VM and open inbound TCP 80/443 for host Caddy.
  Keep TCP 8080, PostgreSQL, Redis, and PHP-FPM off the public interface.
- Install Docker Engine and its Compose plugin, plus host Caddy. Install the
  versioned [`Caddyfile.production.example`](../docker/caddy/Caddyfile.production.example)
  as `/etc/caddy/Caddyfile`, validate it with `caddy validate --config
  /etc/caddy/Caddyfile`, then reload Caddy. Caddy's automatic HTTPS requires
  working DNS and inbound 80/443.
- Choose the **final MiLog UI hostname** and set `MILOG_UI_URL` to its HTTPS
  origin. Signup verification links use this value. The API origin is fixed at
  `https://api.milog.ca` in production Compose. Browsers use the UI origin;
  the UI server calls the API, so production API CORS allows no browser origins.
- Arrange off-VM encrypted backups with a tested restore, adequate retention,
  and a monitoring destination before accepting data. Back up PostgreSQL and
  the production environment secrets, including the Passport signing keys.
  A database backup without the corresponding keys cannot restore existing
  access-token behavior. Do not rely on the 50 GB VM disk as the only copy.
- Confirm the SMTP host, port, TLS mode, username, password, and sender. The PHP
  container has an outbound network for SMTP; PostgreSQL and Redis remain on
  an internal network with named persistent volumes.
- Billing and paid-key activation are unfinished. Do not enable a paid offer
  based on this deployment alone.

## Build and pin a release

### Publish with GitHub Actions (recommended)

The manually triggered `Publish API images` workflow in
`.github/workflows/publish-api-images.yml` runs the PostgreSQL-backed PHPUnit
suite before publishing the production PHP and Nginx images from the same
commit. It uses GitHub's built-in `GITHUB_TOKEN`; do not add the production
pull tokens, application keys, database password, or SMTP credentials as build
secrets. Test credentials, the test application key, and the Passport signing
pair are generated for the disposable test checkout. The fresh runner needs
`passport:keys` before authentication tests; production keys are never used.

1. Commit and push the workflow and release source to GitHub. The workflow
   must exist on the default branch before GitHub shows its manual trigger.
2. In `milogio/milog-api`, open **Actions → Publish API images → Run workflow**.
   Select the reviewed release branch (normally the default branch) and run it.
   The workflow only publishes when manually requested; pushes do not publish.
3. Organization/repository Actions policies must allow the pinned official
   `actions/checkout` and Docker actions. Organization package-creation policy
   must permit this repository to create private container packages. The
   publishing job requests `packages: write`; no publishing PAT is required.
4. Wait for both the test and publishing jobs to succeed. Under the run's
   **Summary**, copy `MILOG_PHP_IMAGE` and `MILOG_NGINX_IMAGE` into the API VM's
   `/opt/milog-api/docker/production.env`. Use the pair from one successful
   run, never a partial pair from a failed run.
5. Verify both packages are private under the organization **Packages** page.
   New GHCR packages default to private. They are associated with this source
   repository. Ensure the account behind each server pull token has read
   access to its required package and any required SSO authorization.

The workflow derives the namespace from the repository owner. For this
repository it publishes `ghcr.io/milogio/milog-api-php` and
`ghcr.io/milogio/milog-api-nginx`. Images target `linux/amd64`, matching the
planned x86-64 FullHost VM; verify with `uname -m` (`x86_64`) before deploying.
Change the workflow platform if provisioning a different architecture.

Each image is labelled with its source and commit. Tags include the full
commit, run ID, and attempt, so a rerun does not overwrite the prior run's
release tag. Production always uses the reported digests, not these tags.
Keep earlier deployed image pairs for rollback. This workflow does not deploy,
run production migrations, or establish live-service readiness.

If publishing fails with `denied` or `403`, check organization Actions/package
policies and the package's **Manage Actions access** settings. An existing
package created manually may need this repository granted write access.
To verify production pull access, authenticate on the API VM with
`sudo docker login ghcr.io --username chrsc` using its classic `read:packages`
token, then run the production Compose `pull` command below. Login success
alone does not prove permission to read a particular private package.

### Manual build alternative

Build on a trusted build host from a reviewed commit. Use one unique release
tag for both images, push them, then copy their registry digests into
`MILOG_PHP_IMAGE` and `MILOG_NGINX_IMAGE`. The VM pulls by digest; it never
builds application images or uses a moving production tag.

```sh
docker build --pull -f docker/php-fpm/production.Dockerfile -t REGISTRY/milog-api/php:RELEASE .
docker build --pull -f docker/nginx/production.Dockerfile -t REGISTRY/milog-api/nginx:RELEASE .
docker push REGISTRY/milog-api/php:RELEASE
docker push REGISTRY/milog-api/nginx:RELEASE
docker buildx imagetools inspect REGISTRY/milog-api/php:RELEASE
docker buildx imagetools inspect REGISTRY/milog-api/nginx:RELEASE
```

Record the tested commit, both image digests, and the current database schema
version with the release. The `docker/production.env.example` file shows all
deployment inputs. Create `docker/production.env` on the VM with mode `0600`
and restrict access to its owner. It is excluded from Git and Docker build
contexts. Do not paste its contents into tickets, logs, or `docker compose
config` output. Provide production values through a protected deployment
environment where possible.

## First start

Generate a Laravel application key and Passport signing key pair **once per
environment** on a trusted workstation. For example, use `php artisan
key:generate --show` and `php artisan passport:keys` with the project code and
dependencies available locally. Base64-encode the Passport key files without
line wrapping for the `MILOG_PASSPORT_*_B64` values. Keep the private key and
application key secret; do not regenerate either on routine releases. Do not
put raw keys in images or the repository.

On the API VM, authenticate to the registry and run:

```sh
docker compose --env-file docker/production.env -f docker-compose.production.yml config --quiet
docker compose --env-file docker/production.env -f docker-compose.production.yml pull
docker compose --env-file docker/production.env -f docker-compose.production.yml up -d postgres redis api
docker compose --env-file docker/production.env -f docker-compose.production.yml run --rm --no-deps api php artisan migrate --force
docker compose --env-file docker/production.env -f docker-compose.production.yml run --rm --no-deps api php artisan passport:client --personal --provider=users --name="MiLog UI"
docker compose --env-file docker/production.env -f docker-compose.production.yml up -d nginx
```

The `run ... api php artisan` form is deliberate: it starts a new API
container through `milog-production-entrypoint`, which reads the Compose
secrets. `docker compose exec api php artisan ...` does not inherit the
entrypoint's exported secrets and must not be used for administration. Treat
the Passport client output as sensitive and create the personal-access client
only once per environment. Use the same `run` form for tenant grants and other
Laravel administrative commands.

The first `up` waits for PostgreSQL and Redis container health. Confirm they
are ready before migrations. The API container's PHP-FPM health check is
liveness; Nginx's `/ready` health check verifies Laravel, PostgreSQL, and
Redis. The Nginx `/health` endpoint is lightweight liveness only.

## Validate and monitor

From the API VM, check both local endpoints. `/ready` returns only `ready` or
`unavailable`; a failed dependency returns HTTP 503 without diagnostics.
Caddy intentionally returns 404 for public `/ready` requests.

```sh
curl -fsS -H 'Host: api.milog.ca' http://127.0.0.1:8080/health
curl -fsS -H 'Host: api.milog.ca' http://127.0.0.1:8080/ready
docker compose --env-file docker/production.env -f docker-compose.production.yml ps
curl -fsS https://api.milog.ca/health
```

After startup, test signup verification email through the UI and a safe
tenant-scoped API smoke flow. Monitor disk capacity, backup completion,
container health, SMTP delivery, and TLS renewal. Container logs are rotated.
Nginx access logs omit URI, query strings, and request headers; the example
Caddyfile does not enable request access logging. Keep `APP_DEBUG=false` and
avoid adding request bodies, authorization headers, API keys, or verification
tokens to any log or observability pipeline.

## Backups and compatible rollback

Before each migration, confirm an off-VM backup and a tested restore path. A
logical database snapshot can be made through the PostgreSQL container without
publishing its port. Stream it directly to protected backup storage rather
than leaving it on the 50 GB VM:

```sh
docker compose --env-file docker/production.env -f docker-compose.production.yml exec -T postgres sh -c 'PGPASSWORD="$(cat /run/secrets/db_password)" pg_dump -h 127.0.0.1 -U milog -Fc milog' > milog-backup.dump
```

Transfer the dump and secret backup off the VM promptly, encrypt them, and
verify restore regularly. The displayed command writes a local file only as
an example; keep that file access-restricted until transfer and removal.

For an application rollback, replace both image digests in
`docker/production.env` with the prior tested pair, pull, and recreate `api`
and `nginx`. This is safe only while the database schema remains compatible
with that pair. Review migrations before release and favor additive changes.
If a migration is incompatible, restore the matching database backup and
secrets under a separately rehearsed recovery procedure; do not run an older
image against an incompatible schema. Never rotate Passport or application
keys as part of a routine rollback.
