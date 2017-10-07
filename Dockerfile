FROM xlight/docker-php7-swoole

ADD . /proxy-server

CMD php http-proxy.php
