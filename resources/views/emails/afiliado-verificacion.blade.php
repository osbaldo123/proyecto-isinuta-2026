<x-mail::message>
# Verificación de cuenta

Hola **{{ $nombre }}**,

Se creó su acceso de consulta en el sistema de la Asociación de Agua Potable y Alcantarillado Isinuta.

## Su código de verificación

Ingrese este código en la pantalla de verificación (válido por {{ $ttlMinutes }} minutos):

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

@if ($password)
## Credenciales de acceso

- **Correo / usuario:** {{ $email }}
- **Contraseña temporal:** {{ $password }}
- También puede iniciar sesión con su **CI** (8 dígitos) y esta contraseña.

Por seguridad, cambie la contraseña después de verificar su correo.
@endif

<x-mail::button :url="$loginUrl">
Ir al inicio de sesión
</x-mail::button>

Si usted no solicitó esta cuenta, ignore este mensaje.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
