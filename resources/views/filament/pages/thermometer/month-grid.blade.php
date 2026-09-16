<table class="thermometer-month">
    <caption class="thermometer-month-caption">{{ $month['label'] }}</caption>

    <thead>
        <tr>
            <th scope="col" class="thermometer-col-day">dia</th>

            @foreach ($this->columns as $type)
                <th scope="col" class="thermometer-col-type">
                    <span class="inline-flex items-center gap-1">
                        @include('filament.pages.thermometer.type-badge', ['type' => $type, 'filled' => true])
                        <span>{{ $type->columnLabel() }}</span>
                    </span>
                </th>
            @endforeach

            <th scope="col" class="thermometer-col-balance">saldos</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($month['days'] as $day)
            @include('filament.pages.thermometer.day-row', [
                'day' => $day,
                'isCurrentMonth' => $isCurrentMonth,
            ])
        @endforeach
    </tbody>

    <tfoot>
        <tr>
            <th scope="row">total</th>

            @foreach ($this->columns as $type)
                <td>
                    <span class="inline-flex items-center justify-end gap-1">
                        @include('filament.pages.thermometer.type-badge', ['type' => $type, 'filled' => true])
                        <span>{{ $this->formatMoney($month['totals'][$type->value]) }}</span>
                    </span>
                </td>
            @endforeach

            <td class="thermometer-total-balance"></td>
        </tr>
    </tfoot>
</table>
