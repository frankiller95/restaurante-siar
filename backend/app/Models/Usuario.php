<?php

namespace App\Models;

use App\Enums\EstadoUsuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * Entidad Usuario (docs/Diagrama_de_clases.md).
 *
 * @property int $id_usuario
 * @property string $nombres
 * @property string $documento
 * @property string $correo
 * @property string $contrasena_cifrada
 * @property EstadoUsuario $estado
 * @property int $id_rol
 * @property-read Rol $rol
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    /** Columna que Laravel usa como contraseña. */
    protected $authPasswordName = 'contrasena_cifrada';

    protected $fillable = [
        'nombres',
        'documento',
        'correo',
        'contrasena_cifrada',
        'estado',
        'id_rol',
    ];

    protected $hidden = [
        'contrasena_cifrada',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoUsuario::class,
            // RNF04: la contraseña siempre se guarda cifrada (bcrypt).
            'contrasena_cifrada' => 'hashed',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function bitacora(): HasMany
    {
        return $this->hasMany(BitacoraAuditoria::class, 'id_usuario', 'id_usuario');
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::ACTIVO;
    }

    /** Verifica la contraseña contra el hash guardado. */
    public function autenticar(string $contrasena): bool
    {
        return Hash::check($contrasena, $this->contrasena_cifrada);
    }

    /** @return list<string> códigos de permiso del rol del usuario */
    public function codigosPermiso(): array
    {
        return $this->rol?->permisos->pluck('codigo_permiso')->values()->all() ?? [];
    }

    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->codigosPermiso(), true);
    }

    /** Cierra todas las sesiones (tokens) activas del usuario. */
    public function revocarSesiones(): int
    {
        return $this->tokens()->delete();
    }

    /** El correo es el identificador para recuperar contraseña. */
    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }
}
