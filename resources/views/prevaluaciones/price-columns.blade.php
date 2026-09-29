@php
    $foodPrices = $prices->values();
    $columnLength = (int) ceil($foodPrices->count() / 2);
@endphp
<style>
    .cafe-price-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .cafe-price-grid thead th { width: 50%; padding: 5px 7px; text-align: left; background: #8c2236; color: #fff; font-size: 7pt; }
    .cafe-price-grid td.food-cell { width: 50%; padding: 4px 7px; border: 1px solid #dfd3d7; vertical-align: top; font-size: 6.7pt; line-height: 1.25; word-wrap: break-word; }
    .cafe-price-grid tbody tr:nth-child(even) td { background: #fbf8f9; }
    .cafe-price-grid .food-name { color: #532036; font-weight: bold; }
    .cafe-price-grid .food-price { color: #532036; font-weight: bold; white-space: nowrap; }
    .cafe-price-grid .food-meta { margin-top: 2px; }
    .cafe-price-grid .food-label { color: #76636c; font-weight: bold; }
    .cafe-price-grid .food-rating { font-weight: bold; }
    .cafe-price-grid td.food-empty { border: 0; background: white !important; }
</style>
@if ($foodPrices->isEmpty())
    <p>No hay precios capturados en esta propuesta.</p>
@else
    <table class="cafe-price-grid">
        <thead><tr>
            <th>Alimentos 1 a {{ $columnLength }}</th>
            <th>Alimentos {{ $columnLength + 1 }} a {{ $foodPrices->count() }}</th>
        </tr></thead>
        <tbody>
        @for ($index = 0; $index < $columnLength; $index++)
            <tr>
                @foreach ([$foodPrices->get($index), $foodPrices->get($index + $columnLength)] as $column => $food)
                    @if ($food)
                        @php($item = $items->get($food->clave))
                        <td class="food-cell">
                            <div><span class="food-name">{{ $index + 1 + ($column * $columnLength) }}. {{ $food->nombre }}</span>{{ $food->unidad ? ' · '.$food->unidad : '' }} <span class="food-price">${{ number_format((float) $food->precio, 2) }}</span></div>
                            <div class="food-meta"><span class="food-label">Calificación:</span> <span class="food-rating">{{ $rating($item?->cumple) }}</span></div>
                            <div class="food-meta"><span class="food-label">Observaciones:</span> {{ $item?->comentario ?: '—' }}</div>
                        </td>
                    @else
                        <td class="food-empty"></td>
                    @endif
                @endforeach
            </tr>
        @endfor
        </tbody>
    </table>
@endif
