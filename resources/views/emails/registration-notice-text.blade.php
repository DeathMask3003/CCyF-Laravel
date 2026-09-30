CCyF · Colegio de Bachilleres del Estado de México

{{ $audience === 'participant' ? 'Recibimos tu propuesta.' : 'Se registró una nueva propuesta.' }}

Folio: {{ $record->folio }}
Participante: {{ $record->solicitante }}
Convocatoria: {{ $record->convocatoria_nombre }}
Servicio: {{ $record->servicio_nombre }}
Plantel: {{ $record->plantel_nombre }}
Documentos recibidos: {{ $record->document_count }}

Consulta el expediente: {{ $audience === 'participant' ? route('oficios.receipt', $record->id) : route('revision.show', $record->id) }}

Conserva el folio para dar seguimiento al registro.
