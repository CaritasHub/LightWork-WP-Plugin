# LightWork WP Plugin Development

## Building a Release

Run `./build_release.sh` to create a tarball of the plugin. The archive will be placed in the `Release/` directory and named according to the version defined inside `lightwork-wp-plugin.php`.

## Local Testing with Docker

1. Build the plugin archive with `./build_release.sh`.
2. Start the environment with `docker-compose up --build`.
3. WordPress will be available on [http://localhost:8080](http://localhost:8080) with the plugin already installed.

