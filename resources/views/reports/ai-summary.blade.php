{{-- Every value is output with {{ }} so it is HTML-escaped. Never use {!! !!} here. --}}
<section class="ai-summary">
    @if(!empty($analysis['title']))
        <h2>{{ $analysis['title'] }}</h2>
    @endif
    <p>{{ $analysis['summary'] }}</p>

    @foreach($analysis['blocks'] as $block)
        <section class="ai-block ai-{{ $block['type'] }}">
            <h3>{{ $block['title'] }}</h3>

            @switch($block['type'])
                @case('metric_comparison')
                    <table>
                        <thead><tr><th>Metric</th><th>Previous</th><th>Current</th><th>Change</th></tr></thead>
                        <tbody>
                        @foreach($block['items'] as $i)
                            <tr>
                                <td>{{ $i['label'] }}</td>
                                <td>{{ $i['previous'] !== null ? number_format($i['previous']) : '–' }} {{ $i['unit'] }}</td>
                                <td>{{ $i['current'] !== null ? number_format($i['current']) : '–' }} {{ $i['unit'] }}</td>
                                <td>{{ $i['change_pct'] !== null ? ($i['change_pct'] > 0 ? '+' : '') . $i['change_pct'] . '%' : '–' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @break

                @case('chart')
                    <div class="ai-chart">
                        <canvas data-chart="{{ json_encode(['chart_type' => $block['chart_type'], 'labels' => $block['labels'], 'series' => $block['series']]) }}"></canvas>
                    </div>
                    @break

                @case('table')
                    <div class="table-scroll">
                        <table>
                            <thead><tr>@foreach($block['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                            <tbody>
                            @foreach($block['rows'] as $row)
                                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @break

                @case('activity_log')
                    <ul>
                        @foreach($block['items'] as $i)
                            <li>{{ $i['label'] }}: <strong>{{ $i['count'] }}</strong></li>
                        @endforeach
                    </ul>
                    @break

                @case('item_list')
                    <ul>
                        @foreach($block['items'] as $i)
                            <li>
                                @if($i['heading'])<strong>{{ $i['heading'] }}</strong>@endif
                                @if($i['text'])<div>{{ $i['text'] }}</div>@endif
                                @if($i['stats'])<small>{{ $i['stats'] }}</small>@endif
                            </li>
                        @endforeach
                    </ul>
                    @break
            @endswitch

            @if($block['note'])<p class="note">{{ $block['note'] }}</p>@endif
            @if($block['source'])<p class="source">Source: {{ $block['source'] }}</p>@endif
        </section>
    @endforeach

    @if(!empty($analysis['confidence_notes']))
        <p class="note"><strong>AI notes:</strong> {{ $analysis['confidence_notes'] }}</p>
    @endif

    @if(!empty($analysis['files_analyzed']))
        <details>
            <summary>Files analyzed</summary>
            <ul>
                @foreach($analysis['files_analyzed'] as $f)
                    <li>{{ $f['name'] }}: {{ $f['status'] }}@if($f['note']) ({{ $f['note'] }})@endif</li>
                @endforeach
            </ul>
        </details>
    @endif
</section>