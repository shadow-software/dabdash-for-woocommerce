# Live smoke (cannabisdigest.net)

Pre–wp.org gate. Full registry: `shadow-agent-markdown/registry/PLUGIN-SMOKE-CI.md`.

## CI

Workflow: `.github/workflows/live-smoke.yml`  
Triggers: semver tags, `workflow_dispatch`.

## Local run

```bash
export SMOKE_SSH_HOST=root@93.95.229.147
export SMOKE_SSH_KEY=/path/to/deploy/key
export SMOKE_WP_PATH=/var/www/cannabisdigest.net/public
export SMOKE_SITE_URL=https://cannabisdigest.net
export SMOKE_WP_USER=cannabisdigest_admin
export SMOKE_WP_APP_PASSWORD=…
export SMOKE_PLUGIN_SLUG=dabdash-for-woocommerce

bash .github/scripts/live-smoke-deploy.sh   # needs SMOKE_ZIP_FILE
bash tests/live-smoke/rest-smoke.sh
bash tests/live-smoke/remote-smoke.sh tests/live-smoke/run-smoke.php
```

On failure after deploy: `bash .github/scripts/live-smoke-rollback.sh`
