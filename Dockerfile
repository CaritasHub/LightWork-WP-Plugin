FROM wordpress:latest

# Install unzip for extracting the plugin
RUN apt-get update \
    && apt-get install -y unzip \
    && rm -rf /var/lib/apt/lists/*

# Copy plugin archive into image
COPY Release/*.zip /tmp/plugin.zip

# Extract plugin
RUN mkdir -p /usr/src/wordpress/wp-content/plugins/lightwork-plugin \
    && unzip /tmp/plugin.zip -d /usr/src/wordpress/wp-content/plugins/lightwork-plugin \
    && rm /tmp/plugin.zip

