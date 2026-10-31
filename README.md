# FileBot Job Handler

Symfony API that queues FileBot AMC jobs through Messenger and Redis.

## Run

Start the development stack with Docker Compose:

```sh
docker compose --profile dev up --build
```

The API is available at `http://localhost:8080`. Check it with
`GET /api/health`.

The job dashboard is available at `http://localhost:8080/`. It shows queued
and processing jobs from the Messenger transport, including workers associated
with pending jobs. It also shows the latest 50 completed, failed, or deleted
jobs. History is retained in separate Redis keys for 7 days after a job
finishes, and the live queue and history sections refresh automatically every
10 seconds. The Docker Compose Redis service enables append-only persistence;
deployed Redis should also have persistence enabled to preserve history across
restarts.

Browse the interactive Swagger UI at `http://localhost:8080/api/doc`. The
generated OpenAPI document is available at `/api/doc.json` and `/api/doc.yaml`.

## Queue a job

Send a `POST` request to `/api/jobs` with JSON such as:

```json
{
  "path": "/torrents/Example.Movie.2024",
  "label": "movie",
  "action": "test",
  "conflict": "skip",
  "mediaId": "123456"
}
```

`path` must be an absolute path visible inside the PHP and worker containers.
The API checks that it exists and contains media before creating and queuing
the job message. The worker then runs FileBot. The API
returns `202 Accepted` after the message is queued.

`action` accepts `test`, `copy`, or `move`; `conflict` independently accepts
`skip`, `replace`, `auto`, `index`, or `fail`. Use `test` to preview FileBot's
changes. `mediaId` is optional and must be numeric. With the default database
settings, use a TMDB ID for movies and a TVDB ID for TV shows.

FileBot failures are not retried automatically; submit a new job with corrected
parameters.

## Logs

FileBot output is sent to Monolog and is available through the Docker logs.

## Configuration

Defaults are declared in `config/services.yaml`; environment variables or
`.env` values override them. `TORRENTS_ROOT` and `STORAGE_ROOT` configure the
Docker volume mount paths. FileBot database and subtitle settings use the
`AMC_*` variables, and `PLEX_HOST` / `PLEX_TOKEN` are optional.

For a production deployment, provide `APP_SECRET`, `DEFAULT_URI`, and
`MESSENGER_TRANSPORT_DSN` as runtime environment variables. Portainer can also
provide the FileBot license path and optional OpenSubtitles credentials to the
worker.

## Container images

The `Build and publish Docker images` workflow publishes the API and worker to
the separate `filebot-handler-api` and `filebot-handler-worker` Docker Hub
repositories. Pull requests build without publishing. Pushes to `main` publish
`latest`, `main`, and a commit SHA tag; pushes to `develop` publish `develop`
and a commit SHA tag. Pushing a `vX.Y.Z` Git tag publishes `X.Y.Z`, `X.Y`, and
`X` tags, with the leading `v` removed. Set the `DOCKERHUB_USERNAME` and
`DOCKERHUB_TOKEN` repository secrets to enable publishing. The workflow
publishes only; it does not deploy to the NAS.

## Tests

Run the unit and integration suite with:

```sh
vendor/bin/phpunit
```
