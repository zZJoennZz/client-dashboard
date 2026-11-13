// npm install chart.js react-chartjs-2
import { Bar, Line, Pie } from 'react-chartjs-2';
import 'chart.js/auto'; // registers all chart types/scales

const CHART_TYPES = { bar: Bar, line: Line, pie: Pie };
const PALETTE = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

const fmt = (n) => (n === null || n === undefined ? '–' : Number(n).toLocaleString());
const pct = (n) => (n === null || n === undefined ? '–' : `${n > 0 ? '+' : ''}${n}%`);

function ReportChart({ block }) {
    const Chart = CHART_TYPES[block.chart_type];
    if (!Chart) return null;

    const isPie = block.chart_type === 'pie';
    const datasets = block.series.map((s, i) => ({
        label: s.name,
        data: s.data,
        // pie needs one color per slice; bar/line need one color per series
        backgroundColor: isPie
            ? block.labels.map((_, j) => PALETTE[j % PALETTE.length])
            : PALETTE[i % PALETTE.length],
        borderColor: isPie ? undefined : PALETTE[i % PALETTE.length],
    }));

    return (
        <div className="ai-chart" style={{ maxWidth: 640 }}>
            <Chart data={{ labels: block.labels, datasets }} options={{ responsive: true }} />
        </div>
    );
}

function Block({ block }) {
    switch (block.type) {
        case 'metric_comparison':
            return (
                <table>
                    <thead>
                        <tr><th>Metric</th><th>Previous</th><th>Current</th><th>Change</th></tr>
                    </thead>
                    <tbody>
                        {block.items.map((i, k) => (
                            <tr key={k}>
                                <td>{i.label}</td>
                                <td>{fmt(i.previous)} {i.unit}</td>
                                <td>{fmt(i.current)} {i.unit}</td>
                                <td>{pct(i.change_pct)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            );

        case 'chart':
            return <ReportChart block={block} />;

        case 'table':
            return (
                <div style={{ overflowX: 'auto' }}>
                    <table>
                        <thead>
                            <tr>{block.columns.map((c, k) => <th key={k}>{c}</th>)}</tr>
                        </thead>
                        <tbody>
                            {block.rows.map((row, r) => (
                                <tr key={r}>{row.map((cell, c) => <td key={c}>{cell}</td>)}</tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );

        case 'activity_log':
            return (
                <ul>
                    {block.items.map((i, k) => (
                        <li key={k}>{i.label}: <strong>{i.count}</strong></li>
                    ))}
                </ul>
            );

        case 'item_list':
            return (
                <ul>
                    {block.items.map((i, k) => (
                        <li key={k}>
                            {i.heading && <strong>{i.heading}</strong>}
                            {i.text && <div>{i.text}</div>}
                            {i.stats && <small>{i.stats}</small>}
                        </li>
                    ))}
                </ul>
            );

        default:
            return null; // unknown block types are ignored
    }
}

/** @param {{ analysis: object|null }} props  analysis = report.ai_analysis.summary_json */
export default function ReportAiSummary({ analysis }) {
    if (!analysis) return null;

    return (
        <section className="ai-summary">
            {analysis.title && <h2>{analysis.title}</h2>}
            <p>{analysis.summary}</p>

            {analysis.blocks?.map((block, k) => (
                <section key={k} className={`ai-block ai-${block.type}`}>
                    <h3>{block.title}</h3>
                    <Block block={block} />
                    {block.note && <p className="note">{block.note}</p>}
                    {block.source && <p className="source">Source: {block.source}</p>}
                </section>
            ))}

            {analysis.confidence_notes && (
                <p className="note"><strong>AI notes:</strong> {analysis.confidence_notes}</p>
            )}

            {analysis.files_analyzed?.length > 0 && (
                <details>
                    <summary>Files analyzed</summary>
                    <ul>
                        {analysis.files_analyzed.map((f, k) => (
                            <li key={k}>{f.name}: {f.status}{f.note ? ` (${f.note})` : ''}</li>
                        ))}
                    </ul>
                </details>
            )}
        </section>
    );
}