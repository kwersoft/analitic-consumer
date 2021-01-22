FROM php:7.4

RUN set -eux ; \
    apt-get update -yqq &&  \
    apt-get install -yqq    \
    libpq-dev               \
    librabbitmq-dev         \
	libxml2-dev             \
    libzip-dev              \
    libpng-dev              \
    curl                    \
    wget					\
	cron					\
	rsyslog					\
	git						\
	zip 					\
	unzip 					\
	&& apt-get clean

RUN set -eux ; docker-php-ext-install bcmath sockets gd mysqli
RUN set -eux ; pecl install amqp
RUN set -eux ; docker-php-ext-enable amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY cron /etc/cron.d/sample
COPY ./ /var/www/

WORKDIR /var/www

CMD set -eux ; composer install && service rsyslog start && service cron start && touch /var/log/deamon.log && tail -f /var/log/deamon.log