# ¿Qué es esto?
Este repositorio contiene el código utilizado por https://subtitulamos.tv.

# Colaboración
¡Gracias por tu interés en colaborar! Para poder contribuir al proyecto primero vas a necesitar un entorno en el que desarrollar: Hay un tutorial sobre el software necesario e instrucciones adicionales un poco más abajo. 

## Normas de colaboración
A la hora de colaborar, sigue las siguientes normas:

- Dale formato al código con [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) antes de crear una Pull Request. Las reglas de formato están incluidas en el repositorio, en el archivo `php-cs.dist`, que PHP-CS-Fixer detectará automáticamente.
- El código está en inglés, así que debes utilizar nombres de variables, comentarios, constantes también en inglés, así como mensajes de commit de Git.

## ¿Qué cosas puedo contribuir?
Puedes ayudar con cualquier issue (error/característica/cambio) que se encuentre abierto, aceptado, y no siendo trabajado ya por algún usuario. No obstante, si tu intención es introducir un cambio o nueva funcionalidad que no tenga un issue ya creado, **debes asegurarte** de comprobar que realmente existe interés por dicho cambio o funcionalidad, creando un hilo en [el foro](https://foro.subtitulamos.tv) o abriendo un nuevo issue en el repositorio.

# Instalación

## SO

Se recomienda la utilización de un sistema UNIX/Linux, puesto que la mayoría de dependencias no están diseñadas para funcionar en otros sistemas operativos, como Windows. Vagrant es una buena alternativa si tu sistema operativo no es UNIX/Linux.

## Software necesario

>*Atención*: No se proveen las instrucciones de instalación del software detallado a continuación, puesto que la guía acabaría siendo demasiado larga. Todas las dependencias son populares y tienen buena documentación disponible en internet.

- PHP 7.1+
    - Junto con [phpredis](https://github.com/phpredis/phpredis). Disponible también via PECL (`pecl install redis`).
- Redis 3+
- MySQL
- Nginx + php-fpm (existe un apéndice al final de este documento con una posible configuración de Nginx) / Apache
- Composer
- NodeJS (última versión estable)
- ElasticSearch 5.5+ [[Instrucciones]](https://www.elastic.co/guide/en/elasticsearch/reference/5.5/_installation.html)
    - ElasticSearch utiliza una versión reciente del JDK 8. Es posible que necesites [utilizar un backport del paquete](https://linux-tips.com/t/how-to-install-java-8-on-debian-jessie/349/2).
- Go 1.8+

## Primeros pasos

### La base

Lo primero que hay que hacer, una vez clonado el proyecto localmente (via git clone, por ejemplo) e instalado el software mencionado en la sección anterior, es instalar las dependencias, mediante `composer install` y `npm install`.

Mientras estas se descargan, puedes copiar el fichero `.env.example` y renombrar dicha copia `.env`. A continuación, modifica sus contenidos de acuerdo a la configuración del servidor MySQL que estés usando (username, pwd, table name, etc).

Después de actualizar `.env` y una vez se hayan terminado de instalar todas las dependencias debes ejecutar, desde el root del proyecto, los siguientes comandos:
- `./vendor/bin/doctrine orm:schema:update --force`, para crear el schema en la base de datos.
- `php app/console app:bots:synchronize`, para crear los usuarios bot.

Una vez llegado a este punto el sitio web funcionará, pero sin estilos o javascript. Para ello se debe ejecutar `npm run build`, que se encargará de compilar y copiar los ficheros. Ahora sí que funcionará todo. Bueno, excepto la parte de traducción en tiempo real...:

### Traducción en tiempo real - Instalación

El sitio web utiliza traducción en tiempo real basada en websockets. Puesto que PHP no es ideal para los procesos de larga duración que la tecnología websocket necesita, un sencillo servidor de Go es usado. Para descargarlo, ejecuta `go get github.com/subtitulamos/subtitulamos-translate` (Puede que necesites establecer previamente la variable de entorno `GOPATH`). Dicho comando descargará el repositorio y compilará el binario `subtitulamos-translate`.

Generalmente no necesitarás modificar el código del servidor de Go puesto que toda la lógica se encuentra en la parte de PHP, el servidor de tiempo real solo reenvía información que recibe de PHP a traves de un canal pub-sub de Redis.

Para arrancar el servidor de traducción, ejecuta `$GOPATH/bin/subtitulamos-translate -redis-pubsub-env <envname>`, donde \<envname\> debe ser igual al valor de la variable de entorno `ENVIRONMENT`.  

Ahora sí: Todo listo.

## Desarrollo

Una vez montado todo, descubrirás que los cambios a los ficheros de JS/CSS no se reflejan en el sitio web sin ejecutar `npm run build`. Para hacer el desarrollo más ágil, existe un comando, `npm run watcher`, que se encarga de vigilar las carpetas de javascript y css, recompilando automáticamente cuando se realicen cambios en estas.

Si te parece un rollo ejecutar el servicio de traducción en tiempo real cada vez que te pongas a desarrollar, [supervisord](http://supervisord.org/) es una alternativa relativamente simple y bien documentada que se encarga de arrancar el binario automáticamente. Para más detalle sobre los argumentos de entrada que puede recibir el binario de tiempo real, mira [aquí](https://github.com/subtitulamos/subtitulamos-translate).

## Apéndice: Configuración ejemplo de Nginx 

Esta configuración debería funcionar "out of the box" con el código del sitio, siempre que reemplaces como mínimo las secciones `root` y `upstream` con los paths correctos según tu configuración.

    server {
        listen 80;
        server_name subtitulamos.dev;

        root /path/to/subtitulamos/files;

        location / {
            index index.php index.html;
            try_files $uri $uri/ /index.php?$query_string;
        }

        # Pass the php scripts to declared upstream sv
        location ~ \.php(/|$) {
            # Unmodified fastcgi_params from nginx distribution.
            include fastcgi_params;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $realpath_root;
            try_files $uri $uri/ /index.php$is_args$args;
            fastcgi_pass php;
        }

        location /translation-rt {
            proxy_pass http://127.0.0.1:8080;
            proxy_redirect off;

            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header Host $http_host;
            proxy_set_header X-NginX-Proxy true;

            # For websockets
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
        }
    }

    # Define fastcgi pass upstream (php-fpm must be configured to listen on a unix socket)
    upstream php {
        server unix:</path/to/php-fpm/socket/file>;
    }