FROM wordpress:latest

# Copy plugin archive into image
COPY Release/*.tar.gz /tmp/plugin.tar.gz

# Extract plugin
RUN mkdir -p /usr/src/wordpress/wp-content/plugins/lightwork-plugin \
    && tar -xzf /tmp/plugin.tar.gz -C /usr/src/wordpress/wp-content/plugins/lightwork-plugin --strip-components=1 \
    && rm /tmp/plugin.tar.gz

