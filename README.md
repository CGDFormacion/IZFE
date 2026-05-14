# IZFE

Carpeta de temas Moodle personalizada para la instalación de IZFE.

Este repositorio contiene lo necesario para reproducir el aspecto de `https://pruebaizfe.cgdformacion.com/` en otro contenedor Moodle:

- `theme`: copia completa de la carpeta de temas Moodle activa, incluyendo `boost`, `classic` y `moove`.
- `assets/theme_moove_files`: logo, favicon, fondo de acceso y slider subidos desde la configuración del tema.
- `assets/lang_overrides`: overrides de idioma de `moodledata/lang` para `theme_moove`.
- `config/theme_moove_export.json`: configuración exportada de `mdl_config`, datos de portada y `mdl_config_plugins` para los temas `boost`, `classic` y `moove`.
- `config/category_language_subcategories.json`: estructura de subcategorías por idioma para las categorías principales.
- `scripts/install_moove_izfe.php`: instalador para aplicar la carpeta completa de temas en un contenedor destino.

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

El instalador hace una copia de seguridad de la carpeta `theme` existente antes de reemplazarla, restaura los ficheros del tema usando la API de Moodle, copia los overrides de idioma, aplica el HTML adicional de cabecera/pie, configura `theme=moove` y purga caches.

## Comportamiento de las categorías por idioma

Además de aplicar el tema, el instalador crea automáticamente subcategorías por idioma dentro de estas categorías principales si no existen todavía:

- `Cursos del plan de formación`
- `Formación para nuevas incorporaciones`
- `Guías y manuales`

La estructura que crea está definida en `config/category_language_subcategories.json` y actualmente es esta:

- `Cursos en castellano`
- `Cursos en euskera`

con `idnumber` únicos por categoría principal para evitar colisiones globales en Moodle.

### Cómo se muestra en el aula

- En la página principal, las tres tarjetas siguen apuntando a las categorías principales.
- Al entrar en una de esas categorías, el tema mantiene la vista estándar de Moodle.
- Si esa categoría tiene subcategorías de idioma, el tema muestra por defecto los cursos de la subcategoría en euskera debajo del árbol/listado estándar.
- El usuario puede seguir entrando manualmente en la subcategoría de castellano o en la de euskera desde la propia página de categoría.

### Qué hay que hacer en el aula destino

El instalador crea la estructura de subcategorías, pero no mueve cursos automáticamente entre idiomas. Después de instalar:

1. Entrar en cada categoría principal.
2. Comprobar que existen las subcategorías `Cursos en castellano` y `Cursos en euskera`.
3. Mover cada curso a la subcategoría de idioma que corresponda.

Si una categoría principal no tiene cursos dentro de `Cursos en euskera`, la zona mostrada por defecto quedará vacía hasta que se asignen cursos a esa subcategoría.

## Después de instalar

Revisar permisos si el contenedor destino usa otro usuario web:

```bash
chown -R www-data:www-data /ruta/a/moodle/theme
```

Si el contenedor destino no tiene `git`, se puede descargar el repositorio como ZIP desde GitHub y ejecutar el mismo instalador desde la carpeta descomprimida.
