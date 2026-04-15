# IZFE

Tema Moove personalizado para la instalación Moodle de IZFE.

Este repositorio contiene lo necesario para reproducir el aspecto de `https://pruebaizfe.cgdformacion.com/` en otro contenedor Moodle:

- `theme/moove`: copia completa del tema Moove activo con las modificaciones visuales.
- `assets/theme_moove_files`: logo, favicon, fondo de acceso y slider subidos desde la configuración del tema.
- `assets/lang_overrides`: overrides de idioma de `moodledata/lang` para `theme_moove`.
- `config/theme_moove_export.json`: configuración exportada de `mdl_config` y `mdl_config_plugins` para `theme_moove`.
- `scripts/install_moove_izfe.php`: instalador para aplicar el tema en un contenedor destino.

## Instalación en el contenedor destino

Clonar el repositorio dentro del contenedor destino y ejecutar el instalador indicando la ruta raíz de Moodle, la que contiene `config.php`.

```bash
git clone https://github.com/CGDFormacion/IZFE.git
cd IZFE
php scripts/install_moove_izfe.php /ruta/a/moodle
```

En la instalación actual de origen la ruta web real es `/var/www/ripollet/public`, así que en una instalación equivalente sería:

```bash
php scripts/install_moove_izfe.php /var/www/ripollet/public
```

El instalador hace una copia de seguridad del `theme/moove` existente antes de reemplazarlo, restaura los ficheros del tema usando la API de Moodle, copia los overrides de idioma, configura `theme=moove` y purga caches.

## Después de instalar

Revisar permisos si el contenedor destino usa otro usuario web:

```bash
chown -R www-data:www-data /ruta/a/moodle/theme/moove
```

Si el contenedor destino no tiene `git`, se puede descargar el repositorio como ZIP desde GitHub y ejecutar el mismo instalador desde la carpeta descomprimida.
