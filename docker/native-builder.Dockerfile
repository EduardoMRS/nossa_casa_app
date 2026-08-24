FROM nossa-casa-app:local

ARG ANDROID_COMMAND_LINE_TOOLS=15859902
ARG ANDROID_COMMAND_LINE_TOOLS_SHA256=4e4c464f145a7512b57d088ac6c278c03c9eea610886b35a5e0804e74eedf583

ENV ANDROID_HOME=/opt/android-sdk \
    ANDROID_SDK_ROOT=/opt/android-sdk \
    PATH=/opt/android-sdk/cmdline-tools/latest/bin:/opt/android-sdk/platform-tools:$PATH

RUN apk add --no-cache bash openjdk17-jdk \
    && mkdir -p /opt/android-sdk/cmdline-tools \
    && curl --fail --location --retry 3 \
        "https://dl.google.com/android/repository/commandlinetools-linux-${ANDROID_COMMAND_LINE_TOOLS}_latest.zip" \
        --output /tmp/android-command-line-tools.zip \
    && echo "${ANDROID_COMMAND_LINE_TOOLS_SHA256}  /tmp/android-command-line-tools.zip" | sha256sum -c - \
    && unzip -q /tmp/android-command-line-tools.zip -d /tmp/android-command-line-tools \
    && mv /tmp/android-command-line-tools/cmdline-tools /opt/android-sdk/cmdline-tools/latest \
    && rm -rf /tmp/android-command-line-tools /tmp/android-command-line-tools.zip \
    && yes | sdkmanager --licenses >/dev/null \
    && sdkmanager \
        "build-tools;36.0.0" \
        "cmake;3.22.1" \
        "ndk;27.0.12077973" \
        "platform-tools" \
        "platforms;android-36" \
    && chown -R 1000:1000 /opt/android-sdk

RUN apk add --no-cache gcompat rsync

WORKDIR /var/www/native

ENTRYPOINT ["php", "artisan"]
