# opencitaseg GLPI plugin

Este es un esqueleto para los plugins que hagamos, tiene la base del plugin vacío de Teclib + unos scripts pre commit que corren phpcs y phpstan.

## Uso

Para clonarlo al iniciar un nuevo proyecto, se debe usar `git archive`

```bash
mkdir nuevo-plugin
cd nuevo-plugin
git archive --remote=git@git.opensa.com.ar:opensa/glpi-plugins/opencitaseg.git HEAD | tar -x
git init
git commit -m "Nuevo proyecto :)"
```
Una vez clonado, se tiene que tirar un `composer install`o `composer update` para que instale y configure phpstan, phpscs, phpcbf y el script pre commit.
Después seguiría crear un proyecto vacío en gitlab para el nuevo plugin y configurar el remote del nuevo proyecto para que apunte a ese repo...

### Cosas a tener en cuenta
1. El plugin tiene el nombre "opencitaseg" en varios lados, habría que modificarlo cuando se use.
2. El archivo de configuración de phpstan `phpstan.neon` solamente tiene en cuenta los archivos `hook.php`, `setup.php` y la carpeta `src/`, si el plugin va a utilizar alguna más hay que agregarlas en la sección de `paths:`
3. La carpeta `stubs` contiene la definición de varias constantes de GLPI para que phpstan no se vuelva loco, esta carpeta se debería mover a la raíz de GLPI porque phpneon las toma de ahí. En caso de que ya la tengas en tu GLPI hay que borrarla para que no cause errores.
4. En `tools/` está el header que debería ir en todos los archivos que contienen código con la licencia GPLv2 que usarían los plugins, en si mucho no importa pero está bueno tenerlo en cuenta.
