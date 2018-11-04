#!/bin/bash

php -dzend_extension=xdebug.so vendor/bin/phpunit "$@"
