FROM xlight/docker-php7-swoole

ADD . /proxy-server

EXPOSE 9510
CMD php http-proxy.php
