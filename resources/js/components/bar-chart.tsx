/**
 * Dependency-free SVG bar chart: scales are computed by hand, the SVG uses a
 * fixed viewBox and stretches responsively.
 */
export function BarChart({ data, height = 180 }: { data: { label: string; value: number }[]; height?: number }) {
    const width = 560;
    const padding = { top: 16, right: 8, bottom: 28, left: 28 };
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;
    const max = Math.max(4, ...data.map((d) => d.value));
    const niceMax = Math.ceil(max / 4) * 4;
    const band = innerW / Math.max(1, data.length);
    const barW = Math.min(44, band * 0.6);
    const ticks = [0, niceMax / 4, niceMax / 2, (3 * niceMax) / 4, niceMax];
    const y = (v: number) => padding.top + innerH - (v / niceMax) * innerH;

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="h-auto w-full" role="img" aria-label="Turnos por día">
            <defs>
                <linearGradient id="bar" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor="var(--color-chart-1)" stopOpacity="1" />
                    <stop offset="100%" stopColor="var(--color-chart-1)" stopOpacity="0.55" />
                </linearGradient>
            </defs>
            {ticks.map((t) => (
                <g key={t}>
                    <line
                        x1={padding.left}
                        x2={width - padding.right}
                        y1={y(t)}
                        y2={y(t)}
                        className="stroke-zinc-200 dark:stroke-zinc-800"
                        strokeDasharray={t === 0 ? undefined : '3 4'}
                    />
                    <text x={padding.left - 8} y={y(t)} dy="0.32em" textAnchor="end" className="fill-zinc-400 text-[10px]">
                        {t}
                    </text>
                </g>
            ))}
            {data.map((d, i) => {
                const cx = padding.left + band * i + band / 2;
                const top = y(d.value);
                return (
                    <g key={d.label}>
                        <rect x={cx - barW / 2} y={top} width={barW} height={Math.max(0, padding.top + innerH - top)} rx={6} fill="url(#bar)">
                            <title>{`${d.label}: ${d.value} turnos`}</title>
                        </rect>
                        {d.value > 0 && (
                            <text x={cx} y={top - 6} textAnchor="middle" className="fill-zinc-600 text-[11px] font-medium dark:fill-zinc-300">
                                {d.value}
                            </text>
                        )}
                        <text x={cx} y={height - 8} textAnchor="middle" className="fill-zinc-500 text-[11px]">
                            {d.label}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}
