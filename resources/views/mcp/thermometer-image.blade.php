@php
    $badgeMarkup = function (\App\Enums\TransactionType $type, bool $filled): string {
        $symbol = $type->badgeSymbol();

        $glyph = $symbol !== null
            ? '<svg class="thermometer-badge-icon" focusable="false"><use href="#'.e($symbol).'" /></svg>'
            : e($type->badgeLetter());

        return '<span class="thermometer-badge" data-type="'.e($type->value).'" data-filled="'.($filled ? 'true' : 'false').'" aria-hidden="true">'.$glyph.'</span>';
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termômetro — {{ $month['label'] }}</title>
    <style>
        :root {
            --tmb-income: #539853;
            --tmb-expense: #e53131;
            --tmb-daily: #eb4998;
            --tmb-savings: #83cb1c;
            --tmb-card: #7b3dec;
            --tmb-today: #232428;
            --tmb-weekend: #ececed;
            --tmb-border: #dddddd;
            --tmb-text: #232428;
            --tmb-muted: #6b7280;
            --tmb-check: #16a34a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: var(--tmb-text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .thermometer {
            width: 1180px;
            padding: 28px 32px;
        }

        .thermometer-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
        }

        .thermometer-legend {
            margin: 0 0 4px;
            font-size: 12px;
            color: var(--tmb-muted);
        }

        .thermometer-color-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            margin: 12px 0 16px;
            padding: 0;
            list-style: none;
            font-size: 11px;
            color: var(--tmb-muted);
        }

        .thermometer-color-legend .swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 5px;
            border: 1px solid var(--tmb-border);
            border-radius: 3px;
            vertical-align: -2px;
        }

        .swatch-dark-red { background: #991b1b; }
        .swatch-light-red { background: #fee2e2; }
        .swatch-light-yellow { background: #fef3c7; }
        .swatch-light-green { background: #dcfce7; }
        .swatch-dark-green { background: #166534; }

        table.thermometer-month {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--tmb-border);
            border-radius: 8px;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }

        .thermometer-month thead th {
            padding: 6px 8px;
            background: var(--tmb-weekend);
            border-bottom: 1px solid var(--tmb-border);
            font-weight: 600;
            font-size: 11px;
            text-align: right;
            white-space: nowrap;
        }

        .thermometer-month thead th.thermometer-col-day,
        .thermometer-month thead th.thermometer-col-balance {
            text-align: left;
        }

        .thermometer-col-day { width: 48px; }

        .thermometer-col-balance { width: 148px; }

        .thermometer-row > th,
        .thermometer-row > td {
            padding: 0;
            height: 32px;
            border-bottom: 1px solid var(--tmb-border);
        }

        .thermometer-row:last-child > th,
        .thermometer-row:last-child > td {
            border-bottom: 0;
        }

        .thermometer-row-today > th,
        .thermometer-row-today > td {
            border-bottom-color: var(--tmb-text);
        }

        .thermometer-day-cell {
            text-align: center;
            vertical-align: middle;
        }

        .thermometer-day-static {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            height: 32px;
        }

        .thermometer-row-weekend .thermometer-day-static {
            background: var(--tmb-weekend);
        }

        .thermometer-row-today .thermometer-day-static {
            background: var(--tmb-today);
            color: #ffffff;
        }

        .thermometer-day-check {
            width: 12px;
            height: 12px;
            fill: var(--tmb-check);
        }

        .thermometer-row-today .thermometer-day-check {
            fill: #4ade80;
        }

        .thermometer-value-cell {
            padding: 0 8px;
            text-align: right;
            white-space: nowrap;
        }

        .thermometer-value {
            margin-left: 6px;
            color: var(--tmb-text);
            font-weight: 600;
        }

        .thermometer-value-projection {
            color: var(--tmb-muted);
            font-weight: 400;
        }

        .thermometer-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 13px;
            height: 13px;
            border-radius: 999px;
            background: var(--tmb-muted);
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            line-height: 1;
            vertical-align: -2px;
        }

        .thermometer-badge[data-filled='true'][data-type='income'] { background: var(--tmb-income); }
        .thermometer-badge[data-filled='true'][data-type='expense'] { background: var(--tmb-expense); }
        .thermometer-badge[data-filled='true'][data-type='daily'] { background: var(--tmb-daily); }
        .thermometer-badge[data-filled='true'][data-type='savings'] { background: var(--tmb-savings); }
        .thermometer-badge[data-filled='true'][data-type='card'] { background: var(--tmb-card); }

        .thermometer-badge-icon {
            width: 9px;
            height: 9px;
            fill: currentColor;
        }

        .thermometer-balance-cell {
            padding: 0 8px;
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .thermometer-balance-cell[data-balance-color='dark-red'] { background: #991b1b; color: #ffffff; }
        .thermometer-balance-cell[data-balance-color='light-red'] { background: #fee2e2; color: #7f1d1d; }
        .thermometer-balance-cell[data-balance-color='light-yellow'] { background: #fef3c7; color: #78350f; }
        .thermometer-balance-cell[data-balance-color='light-green'] { background: #dcfce7; color: #14532d; }
        .thermometer-balance-cell[data-balance-color='dark-green'] { background: #166534; color: #ffffff; }

        .thermometer-month tfoot th,
        .thermometer-month tfoot td {
            padding: 6px 8px;
            background: var(--tmb-weekend);
            border-top: 2px solid var(--tmb-text);
            font-weight: 600;
            font-size: 11px;
            text-align: right;
            white-space: nowrap;
        }

        .thermometer-month tfoot th {
            text-align: left;
        }
    </style>
</head>
<body>
<svg xmlns="http://www.w3.org/2000/svg" style="display: none" aria-hidden="true" focusable="false">
    <symbol id="tmb-icon-arrow-in" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M14.78 5.22a.75.75 0 0 0-1.06 0L6.5 12.44V6.75a.75.75 0 0 0-1.5 0v7.5c0 .414.336.75.75.75h7.5a.75.75 0 0 0 0-1.5H7.56l7.22-7.22a.75.75 0 0 0 0-1.06Z" clip-rule="evenodd" />
    </symbol>
    <symbol id="tmb-icon-arrow-out" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 0 0 1.06 0l7.22-7.22v5.69a.75.75 0 0 0 1.5 0v-7.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0 0 1.5h5.69l-7.22 7.22a.75.75 0 0 0 0 1.06Z" clip-rule="evenodd" />
    </symbol>
    <symbol id="tmb-icon-check" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
    </symbol>
</svg>

<div class="thermometer">
    <h1 class="thermometer-title">Termômetro — {{ $month['label'] }}</h1>

    @if ($initialBalance !== null)
        <p class="thermometer-legend">
            Saldo inicial de {{ $money($initialBalance['amount']) }} conta a partir de {{ $initialBalance['starts_at'] }}.
        </p>
    @endif

    <p class="thermometer-legend">
        O saldo acumulado soma entradas e subtrai saídas, diários, economias e cartão.
        Dias futuros sem lançamento diário mostram a previsão diária.
    </p>

    <ul class="thermometer-color-legend">
        <li><span class="swatch swatch-dark-red"></span>saldo ≤ −R$ 500,00</li>
        <li><span class="swatch swatch-light-red"></span>−R$ 500,00 &lt; saldo ≤ R$ 0,00</li>
        <li><span class="swatch swatch-light-yellow"></span>R$ 0,00 &lt; saldo ≤ R$ 1.000,00</li>
        <li><span class="swatch swatch-light-green"></span>R$ 1.000,00 &lt; saldo ≤ R$ 2.000,00</li>
        <li><span class="swatch swatch-dark-green"></span>saldo &gt; R$ 2.000,00</li>
    </ul>

    <table class="thermometer-month">
        <colgroup>
            <col class="thermometer-col-day" />
            @foreach ($columns as $type)
                <col />
            @endforeach
            <col class="thermometer-col-balance" />
        </colgroup>

        <thead>
            <tr>
                <th scope="col" class="thermometer-col-day">dia</th>

                @foreach ($columns as $type)
                    <th scope="col">{{ $type->columnLabel() }}</th>
                @endforeach

                <th scope="col" class="thermometer-col-balance">saldos</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($month['days'] as $day)
                @php
                    $filledTypes = array_flip($day['filled_types']);
                    $projectedTypes = array_flip($day['projected_types']);
                @endphp
                <tr @class([
                    'thermometer-row',
                    'thermometer-row-today' => $day['is_today'],
                    'thermometer-row-weekend' => $day['is_weekend'],
                ])>
                    <th scope="row" class="thermometer-day-cell">
                        <span class="thermometer-day-static">
                            <span>{{ $day['day'] }}</span>
                            @if ($day['is_checked_in'])
                                <svg class="thermometer-day-check" aria-hidden="true" focusable="false"><use href="#tmb-icon-check" /></svg>
                            @endif
                        </span>
                    </th>

                    @foreach ($columns as $type)
                        @php
                            $isFilled = isset($filledTypes[$type->value]);
                            $isProjected = isset($projectedTypes[$type->value]);
                        @endphp
                        <td class="thermometer-value-cell">
                            {!! $badgeMarkup($type, $isFilled) !!}
                            <span @class(['thermometer-value', 'thermometer-value-projection' => $isProjected])>{{ $money($day[$type->value]) }}</span>
                        </td>
                    @endforeach

                    <td class="thermometer-balance-cell" data-balance-color="{{ $day['balance_color'] }}">{{ $money($day['balance']) }}</td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr>
                <th scope="row">total</th>

                @foreach ($columns as $type)
                    <td>
                        {!! $badgeMarkup($type, true) !!}
                        <span class="thermometer-value">{{ $money($month['totals'][$type->value]) }}</span>
                    </td>
                @endforeach

                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
</body>
</html>
