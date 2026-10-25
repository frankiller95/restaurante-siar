# SIAR — Backend (API REST en Laravel 12)

Las instrucciones de instalación están en el [README principal](../README.md).

## Organización del código (arquitectura en capas)

| Capa | Carpeta |
|---|---|
| Rutas de la API | `routes/api.php` |
| Controladores | `app/Http/Controllers/Api` |
| Validación de entrada | `app/Http/Requests` |
| DTO de entrada / salida | `app/DTOs` / `app/Http/Resources` |
| Servicios (reglas de negocio) | `app/Services` |
| Contratos (interfaces) | `app/Contracts` |
| Repositorios (acceso a datos) | `app/Repositories/Eloquent` |
| Modelos (entidades) | `app/Models` |
| Eventos y observadores (bitácora) | `app/Events`, `app/Listeners` |
| Control de acceso por permisos | `app/Http/Middleware/VerificarPermiso.php` |

## Comandos útiles

```bash
php artisan migrate:fresh --seed   # recrea la base de datos con los datos iniciales
php artisan test                   # ejecuta las pruebas
php artisan route:list --path=api  # lista las rutas de la API
./vendor/bin/pint                  # formatea el código
```
