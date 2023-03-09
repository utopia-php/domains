FROM phpswoole/swoole:php8.1

RUN apt-get update && \
    apt-get install -y -q \
    apt-utils \
    zsh \
    nano \
    vim \
    make \
    locales \
    g++ \
    gcc \
    git \
    curl \
    debianutils \
    binutils \
    sed \
    curl \
    wget \
    gnupg \
    gnupg2 \
    direnv \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

RUN composer require laravel/pint --dev --ignore-platform-reqs

RUN sh -c "$(curl -fsSL https://raw.github.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"

ADD . /app

WORKDIR /app

ENV TERM xterm-256color

CMD ["/bin/zsh"]