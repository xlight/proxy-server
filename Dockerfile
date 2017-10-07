FROM xlight/docker-php7-swoole

WORKDIR /proxy-server
EXPOSE 9510
CMD php http-proxy.php

ADD . /proxy-server
